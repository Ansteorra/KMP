---
layout: default
---
[← Back to Table of Contents](index.md)

# Appendices

This section contains additional reference information to help developers working with the Kingdom Management Portal.

## A. Troubleshooting

Common issues encountered during development and deployment, with their solutions.

### Database Connection Issues

**Issue**: Unable to connect to the database  
**Solution**: 
- Verify database credentials in `config/app_local.php`
- Check that the MySQL/MariaDB server is running
- Ensure the specified database exists and the user has proper permissions
- Check for IP restrictions on the database server

**Issue**: Migration fails with foreign key errors  
**Solution**:
- Ensure migrations are run in the correct order (core first, then plugins in their defined order)
- Verify that referenced tables exist before creating foreign keys
- Check for typos in table or column names

### Authentication Problems

**Issue**: Unable to log in despite correct credentials  
**Solution**:
- Check for proper session configuration
- Verify the user account is active
- Clear browser cookies and cache
- Check for PHP session garbage collection issues

**Issue**: Unexpected "Access Denied" messages  
**Solution**:
- Verify the user has the required role and permissions
- Check permission policies for conditional authorization
- Look for scoping issues (branch-specific vs. global permissions)
- Enable debug mode temporarily to see authorization failures

### Performance Issues

**Issue**: Slow page loads  
**Solution**:
- Enable the SQL log and look for inefficient queries
- Optimize database indexes for frequently queried fields
- Enable CakePHP query caching
- Check for N+1 query problems and use containable associations

**Issue**: High memory usage  
**Solution**:
- Use pagination for large result sets
- Avoid loading unnecessary associations
- Optimize image sizes in the application
- Increase PHP memory limit if necessary

### Common Error Messages

**Error**: "SQLSTATE[HY000] [2002] Connection refused"  
**Solution**: Database server is not running or is not accessible at the configured host/port

**Error**: "Error: An Internal Error Has Occurred"  
**Solution**: Enable debug mode to see detailed error information, check logs for details

**Error**: "Class 'App\Plugin\MyPlugin\...' not found"  
**Solution**: Verify plugin namespace and class names match directory structure, ensure plugin is properly loaded

## B. Glossary

Terms specific to KMP and the SCA to help new developers understand the domain.

| Term | Definition |
|------|------------|
| **Active Window** | A time period during which an entity (like a warrant) is considered active, defined by start and end dates |
| **Branch** | A geographic unit of the SCA organization (Kingdom, Principality, Barony, etc.) |
| **Member** | An individual user of the system |
| **Office** | An official position within the SCA that can be held by members |
| **Warrant** | Official documentation that a member holds a specific office |
| **Warrant Roster** | A collection of warrants for a specific branch and time period |

## C. Resources

Additional resources and references for KMP developers.

### CakePHP Documentation

- [CakePHP 5.x Book](https://book.cakephp.org/5/en/index.html) - Official CakePHP documentation
- [CakePHP API](https://api.cakephp.org/5.x/) - API reference for CakePHP 5.x
- [CakePHP Cookbook](https://book.cakephp.org/5/en/index.html) - Recipes for common tasks

### KMP-Related Resources

- [KMP GitHub Repository](https://github.com/Ansteorra/KMP) - Source code and issue tracking

### PHP Resources

- [PHP Documentation](https://www.php.net/docs.php) - Official PHP documentation
- [Composer Documentation](https://getcomposer.org/doc/) - PHP dependency management
- [PHP Fig PSR Standards](https://www.php-fig.org/psr/) - PHP coding standards

### JavaScript Resources

- [ECMAScript 6 Features](https://github.com/lukehoban/es6features) - Overview of ES6 features
- [Stimulus JS Documentation](https://stimulus.hotwired.dev/) - Documentation for Stimulus framework
- [Webpack Documentation](https://webpack.js.org/) - Documentation for Webpack

### Tools and Libraries

- [Bootstrap 5.3 Documentation](https://getbootstrap.com/docs/5.3/) - UI framework used by KMP
- [Font Awesome Icons](https://fontawesome.com/icons) - Icon library (via @fortawesome/fontawesome-free)
- [Bootstrap Icons](https://icons.getbootstrap.com/) - Additional icon library used in KMP
- [Laravel Mix Documentation](https://laravel-mix.com/) - Asset compilation tool
