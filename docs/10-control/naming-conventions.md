# File Naming Conventions

## Purpose
Prevent case sensitivity issues and maintain consistent file organization across the project.

## Rules

### 1. Use lowercase with hyphens
- ✅ `00-master-status.md`
- ✅ `phase-3-implementation.md`
- ❌ `00-Master-status.md`
- ❌ `Task-tracker.md`

### 2. No mixed case
- ✅ `v7-implementation-plan.md`
- ❌ `V7-IMPLEMENTATION-PLAN.md`

### 3. Consistent numbering
- ✅ `01-project-status.md`
- ✅ `02-task-tracker.md`
- ❌ `1-project-status.md`

### 4. Directory names
- ✅ `10-control/`
- ✅ `20-planning/`
- ❌ `20-Planning/`

## Enforcement

### Git Configuration
Add to `.gitattributes`:
```
* text=auto eol=lf
```

### Pre-commit Hook
Check for case sensitivity conflicts before commits.

## Migration Checklist
- [x] Remove `00-Master-status.md` duplicate
- [x] Merge `20-Planning` and `20-planning` directories
- [ ] Rename `02-Task-tracker.md` to `02-task-tracker.md`
- [ ] Review all uppercase filenames

## Prevention
1. Always use lowercase filenames
2. Use hyphens instead of underscores or camelCase
3. Run `ls -la` before creating files to check for similar names
4. Configure editor to show case-sensitive warnings