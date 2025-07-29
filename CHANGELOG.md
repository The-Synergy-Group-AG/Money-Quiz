# Changelog

All notable changes to the Money Quiz WordPress Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - 2025-07-28

### Added
- Complete modern PHP architecture with PSR-4 autoloading
- Dependency injection container for better testability
- Service layer pattern for business logic separation
- Repository pattern for data access abstraction
- Comprehensive unit and integration tests
- CSRF token protection for all forms
- Redis caching support with fallback to transients
- GraphQL API for headless WordPress installations
- Microservices architecture support (Swoole, gRPC, Kafka)
- Lazy loading for quiz questions
- Asset minification and optimization
- Performance monitoring and metrics
- Database query optimization with proper indexes
- Admin dashboard with comprehensive management tools
- Settings import/export functionality
- CSV export for quiz results
- Advanced email customization
- Hook system for extensibility
- REST API endpoints
- Security hardening throughout

### Changed
- Migrated from procedural to object-oriented architecture
- Updated minimum PHP version to 7.4
- Improved database schema with proper indexes
- Enhanced admin interface with modern UI
- Optimized frontend JavaScript for better performance
- Refactored shortcode handling for flexibility
- Improved error handling and logging

### Fixed
- SQL injection vulnerabilities in legacy code
- XSS vulnerabilities in output rendering
- Performance issues with large quiz datasets
- Memory leaks in question loading
- JavaScript conflicts with other plugins
- Email delivery issues
- Character encoding problems

### Security
- Added nonce verification to all AJAX calls
- Implemented capability checks for all admin actions
- Added input sanitization throughout
- Secured file uploads and downloads
- Protected against CSRF attacks

## [1.4b] - Previous Version

### Legacy Features
- Basic quiz functionality
- 8 money archetypes
- Multiple quiz lengths (Blitz, Short, Full, Classic)
- Email notifications
- Basic reporting
- WordPress admin integration
- Shortcode support
- License key activation

### Known Issues (Fixed in 4.0.0)
- No proper error handling
- Security vulnerabilities
- Performance issues
- Limited extensibility
- No automated testing
- Poor code organization

## Migration Notes

### Upgrading from 1.4b to 4.0.0

1. **Backup your database** - Critical for safe migration
2. **Test in staging** - Verify compatibility with your theme
3. **Run migration** - Automatic database updates will apply
4. **Update templates** - Custom templates may need updates
5. **Test integrations** - Verify third-party integrations work

### Breaking Changes

- Custom code hooks may need updates
- Template overrides require new structure
- Some filters renamed for consistency
- Database table structure optimized

### Deprecations

- `mq_get_quiz()` - Use service container instead
- Direct database queries - Use repositories
- Global functions - Use class methods
- Legacy hooks - See migration guide

## Support

For questions about upgrading or changelog entries:
- Email: andre@101BusinessInsights.info
- Documentation: See upgrade guide in /docs/

---

*This changelog is maintained by Business Insights Group AG*