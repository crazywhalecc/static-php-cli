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

TARGET=""
if [ -n "$SPC_TARGET" ]; then
    TARGET="-target $SPC_TARGET"
fi

output=$(zig cc $TARGET $COMPILER_EXTRA "${PARSED_ARGS[@]}" 2>&1)
status=$?

if [ $status -ne 0 ] && echo "$output" | grep -q "version '.*' in target triple"; then
    output=$(echo "$output" | grep -v "version '.*' in target triple")
    status=0
fi

echo "$output"
exit $status
