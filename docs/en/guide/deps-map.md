---
aside: false
---

# Dependency Map

This page lists all supported packages (extensions and libraries) together with their dependency relationships.

- **Required Dependencies**: packages that are always built alongside the selected package.
- **Suggested Dependencies**: packages that are not built by default; enable them with `--with-suggests` or by specifying them manually.
- **Required By / Suggested By**: which other packages need or suggest this package.

Run the following command to generate the dependency data (source mode required):

```bash
bin/spc dev:gen-deps-data
```

<DepsMap />
