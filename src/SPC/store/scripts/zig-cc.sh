#!/usr/bin/env bash

SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")"
BUILDROOT_ABS="$(realpath "$SCRIPT_DIR/../../../buildroot/include" 2>/dev/null || true)"
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
        -march=*|-mcpu=*)
            OPT_NAME="${1%%=*}"
            OPT_VALUE="${1#*=}"
            # Skip armv8- flags entirely as Zig doesn't support them
            if [[ "$OPT_VALUE" == armv8-* ]]; then
                shift
                continue
            fi
            # replace -march=x86-64 with -march=x86_64
            OPT_VALUE="${OPT_VALUE//-/_}"
            PARSED_ARGS+=("${OPT_NAME}=${OPT_VALUE}")
            shift
            ;;
        *)
            PARSED_ARGS+=("$1")
            shift
            ;;
    esac
done

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
