#!/usr/bin/env bash

SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")"
BUILDROOT_ABS="$(realpath "$SCRIPT_DIR/../../buildroot/include" 2>/dev/null || true)"
PARSED_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        -isystem)
            shift
            ARG="$1"
            shift
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || true)"
            [[ "$ARG_ABS" == "$BUILDROOT_ABS" ]] && PARSED_ARGS+=("-I$ARG") || PARSED_ARGS+=("-isystem" "$ARG")
            ;;
        -isystem*)
            ARG="${1#-isystem}"
            shift
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || true)"
            [[ "$ARG_ABS" == "$BUILDROOT_ABS" ]] && PARSED_ARGS+=("-I$ARG") || PARSED_ARGS+=("-isystem$ARG")
            ;;
        *)
            PARSED_ARGS+=("$1")
            shift
            ;;
    esac
done

[[ -n "$SPC_TARGET" ]] && TARGET="-target $SPC_TARGET" || TARGET=""

output=$(zig cc $TARGET $COMPILER_EXTRA "${PARSED_ARGS[@]}" 2>&1)
status=$?

if [[ $status -ne 0 ]] && grep -q "version '.*' in target triple" <<< "$output"; then
    output=$(grep -v "version '.*' in target triple" <<< "$output")
    status=0
fi

echo "$output"
exit $status
