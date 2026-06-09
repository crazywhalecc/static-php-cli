#!/usr/bin/env bash

SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")"
BUILDROOT_INC="${BUILD_INCLUDE_PATH:-$SCRIPT_DIR/../../../buildroot/include}"
BUILDROOT_ABS="$(realpath "$BUILDROOT_INC" 2>/dev/null || true)"
PARSED_ARGS=()

is_buildroot_inc() {
    [[ -n "$BUILDROOT_ABS" && "$1" == "$BUILDROOT_ABS" ]]
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        -isystem)
            shift
            ARG="$1"
            shift
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || true)"
            is_buildroot_inc "$ARG_ABS" && PARSED_ARGS+=("-I$ARG") || PARSED_ARGS+=("-isystem" "$ARG")
            ;;
        -isystem*)
            ARG="${1#-isystem}"
            shift
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || true)"
            is_buildroot_inc "$ARG_ABS" && PARSED_ARGS+=("-I$ARG") || PARSED_ARGS+=("-isystem$ARG")
            ;;
        -march=*|-mcpu=*)
            OPT_NAME="${1%%=*}"
            OPT_VALUE="${1#*=}"
            # zig rejects -march=armv8-a but accepts -mcpu=generic+v8a; rewrite
            # armv<X>[.<Y>]-a[+feat] -> generic+v<X>[_<Y>]a[+feat] so it goes through.
            if [[ "$OPT_VALUE" =~ ^armv([89])(\.([0-9]+))?-a(\+.*)?$ ]]; then
                arch_feat="v${BASH_REMATCH[1]}"
                [[ -n "${BASH_REMATCH[3]}" ]] && arch_feat="${arch_feat}_${BASH_REMATCH[3]}"
                OPT_VALUE="generic+${arch_feat}a${BASH_REMATCH[4]}"
            fi
            # zig uses snake_case in CPU/feature names (x86-64 -> x86_64).
            OPT_VALUE="${OPT_VALUE//-/_}"
            PARSED_ARGS+=("${OPT_NAME}=${OPT_VALUE}")
            shift
            ;;
        -Wlogical-op|-Wduplicated-cond|-Wduplicated-branches|-Wno-clobbered|-Wjump-misses-init|-Wformat-truncation|-Warray-bounds=*|-Wimplicit-fallthrough=*)
            # GCC-only warning flags that clang/zig doesn't recognize; drop to silence -Wunknown-warning-option noise
            shift
            ;;
        *)
            PARSED_ARGS+=("$1")
            shift
            ;;
    esac
done

IS_LINK=1
NEED_PROFILE_RT=0 # https://codeberg.org/ziglang/zig/issues/32066
NEED_CRT=0 # https://codeberg.org/ziglang/zig/issues/32064
for _a in "${PARSED_ARGS[@]}"; do
    case "$_a" in
        -c|-S|-E|-M|-MM) IS_LINK=0 ;;
        -fprofile-generate*|-fprofile-instr-generate*|-fcs-profile-generate*) NEED_PROFILE_RT=1 ;;
        -shared) NEED_CRT=1 ;;
    esac
done
[[ "$SPC_COMPILER_EXTRA" == *-fprofile-generate* || "$SPC_COMPILER_EXTRA" == *-fcs-profile-generate* ]] && NEED_PROFILE_RT=1
if [[ $IS_LINK -eq 1 && $NEED_PROFILE_RT -eq 1 && -f "$SCRIPT_DIR/lib/libclang_rt.profile.a" ]]; then
    PARSED_ARGS+=("-x" "none" "$SCRIPT_DIR/lib/libclang_rt.profile.a" "-Wl,-u,__llvm_profile_runtime")
fi
if [[ $IS_LINK -eq 1 && $NEED_CRT -eq 1 && -f "$SCRIPT_DIR/lib/clang_rt.crtbegin.o" && -f "$SCRIPT_DIR/lib/clang_rt.crtend.o" ]]; then
    PARSED_ARGS+=("-x" "none" "$SCRIPT_DIR/lib/clang_rt.crtbegin.o" "$SCRIPT_DIR/lib/clang_rt.crtend.o")
fi
if [[ $IS_LINK -eq 1 && -f "$SCRIPT_DIR/lib/libclang_rt.cpu_model.a" ]]; then
    PARSED_ARGS+=("-x" "none" "$SCRIPT_DIR/lib/libclang_rt.cpu_model.a")
fi

[[ -n "$SPC_TARGET" ]] && TARGET="-target $SPC_TARGET" || TARGET=""

if [[ "$SPC_TARGET" =~ \.[0-9]+\.[0-9]+ ]]; then
    output=$(zig cc $TARGET $SPC_COMPILER_EXTRA "${PARSED_ARGS[@]}" 2>&1)
    status=$?

    if [[ $status -eq 0 ]]; then
        echo "$output"
        exit 0
    fi

    if echo "$output" | grep -qE "version '.*' in target triple"; then
        filtered_output=$(echo "$output" | grep -vE "version '.*' in target triple")
        echo "$filtered_output"
        exit 0
    fi
fi

exec zig cc $TARGET $SPC_COMPILER_EXTRA "${PARSED_ARGS[@]}"
