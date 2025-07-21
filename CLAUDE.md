# Claude AI Development Guidelines

## CRITICAL: Micro-Task Architecture (Mandatory)

### File Size Limits
- **Maximum lines per file: 150**
- **Target lines per file: 100-120**
- **Absolute maximum tokens per operation: 5,000**

### Implementation Strategy
1. **One file per operation** - Never create multiple files in a single response
2. **Clear context after each file** - Don't maintain references between operations
3. **No parallel processing** - Sequential execution only
4. **Component separation** - Break features into 4-6 micro-files

### File Structure Pattern
```
feature-name/
├── core/
│   ├── constants.php (30-50 lines)
│   ├── interfaces.php (30-50 lines)
│   └── exceptions.php (30-50 lines)
├── components/
│   ├── component-1.php (100-150 lines)
│   ├── component-2.php (100-150 lines)
│   └── component-3.php (100-150 lines)
├── integration/
│   └── loader.php (50-100 lines)
```

### Why This Approach Is Mandatory
- Prevents API timeouts and errors
- Ensures consistent progress
- Enables incremental development
- Maintains code quality
- Allows for easy testing and debugging

## Project-Specific Guidelines

### Money Quiz Plugin Structure
- WordPress plugin following coding standards
- Security-first implementation
- Modular architecture
- Comprehensive testing

### Current Development Phase
- Cycle 6: Security Hardening (50% complete)
- Using micro-task architecture
- 42 total micro-tasks
- Average 100-120 lines per file

### Testing Requirements
- Run lint and typecheck commands after implementation
- Use commands from this file or ask user if not available
- Suggest adding commands to CLAUDE.md for future reference

### Security Focus Areas
1. CSRF Protection ✅
2. XSS Prevention ✅
3. SQL Injection Prevention ✅
4. Rate Limiting ✅
5. Security Headers (in progress)
6. Audit Logging
7. Vulnerability Scanning
8. Security Testing

## Commands to Run

### Linting
```bash
# PHP CodeSniffer
vendor/bin/phpcs --standard=WordPress-Extra .

# PHP Stan
vendor/bin/phpstan analyse --level=5
```

### Testing
```bash
# PHPUnit
vendor/bin/phpunit

# Security scan
vendor/bin/security-checker security:check
```

### Build
```bash
# Create production build
npm run build

# Generate documentation
npm run docs
```

## Important Notes
- Never create files larger than 150 lines
- Always use the micro-task approach
- Clear context between file operations
- Track progress incrementally
- Report any issues with file size or complexity