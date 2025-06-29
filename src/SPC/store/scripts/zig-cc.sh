#!/usr/bin/env bash

SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")"
BUILDROOT_ABS="$(realpath "$SCRIPT_DIR/../../buildroot/include" 2>/dev/null || echo "")"
PARSED_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        -isystem)
            shift
            ARG="$1"
            [[ -n "$ARG" ]] && shift || break
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || echo "")"
            if [[ -n "$ARG_ABS" && "$ARG_ABS" == "$BUILDROOT_ABS" ]]; then
                PARSED_ARGS+=("-I$ARG")
            else
                PARSED_ARGS+=("-isystem" "$ARG")
            fi
            ;;
        -isystem*)
            ARG="${1#-isystem}"
            shift
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || echo "")"
            if [[ -n "$ARG_ABS" && "$ARG_ABS" == "$BUILDROOT_ABS" ]]; then
                PARSED_ARGS+=("-I$ARG")
            else
                PARSED_ARGS+=("-isystem$ARG")
            fi
            ;;
        *)
            PARSED_ARGS+=("$1")
            shift
            ;;
    esac
done

SPC_TARGET_WAS_SET=1
if [ -z "${SPC_TARGET+x}" ]; then
    SPC_TARGET_WAS_SET=0
fi

UNAME_M="$(uname -m)"
UNAME_S="$(uname -s)"

case "$UNAME_M" in
    x86_64) ARCH="x86_64" ;;
    aarch64|arm64) ARCH="aarch64" ;;
    *) echo "Unsupported architecture: $UNAME_M" >&2; exit 1 ;;
esac

case "$UNAME_S" in
    Linux) OS="linux" ;;
    Darwin) OS="macos" ;;
    *) echo "Unsupported OS: $UNAME_S" >&2; exit 1 ;;
esac

SPC_TARGET="${SPC_TARGET:-$ARCH-$OS}"

if [ "$SPC_LIBC" = "glibc" ]; then
    SPC_LIBC="gnu"
fi

if [ "$SPC_TARGET_WAS_SET" -eq 0 ] && [ -z "$SPC_LIBC" ] && [ -z "$SPC_LIBC_VERSION" ]; then
    exec zig cc ${COMPILER_EXTRA} "${PARSED_ARGS[@]}"
elif [ -z "$SPC_LIBC" ] && [ -z "$SPC_LIBC_VERSION" ]; then
    exec zig cc -target "${SPC_TARGET}" ${COMPILER_EXTRA} "${PARSED_ARGS[@]}"
else
    TARGET="${SPC_TARGET}-${SPC_LIBC}"
    [ -n "$SPC_LIBC_VERSION" ] && TARGET="${TARGET}.${SPC_LIBC_VERSION}"

    output=$(zig cc -target "$TARGET" -lstdc++ ${COMPILER_EXTRA} "${PARSED_ARGS[@]}" 2>&1)
    status=$?

    if [ $status -eq 0 ]; then
        echo "$output"
        exit 0
    fi

    if echo "$output" | grep -q "version '.*' in target triple"; then
        echo "$output" | grep -v  "version '.*' in target triple"
        exit 0
    else
        exec zig cc -target "$TARGET" ${COMPILER_EXTRA} "${PARSED_ARGS[@]}"
    fi
fi
