# Security Policy

## Supported Versions

We provide security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within this package, please send an email to security@litepie.com. All security vulnerabilities will be promptly addressed.

### What to Include

When reporting a vulnerability, please include:

1. **Description**: A clear description of the vulnerability
2. **Impact**: What an attacker could achieve
3. **Steps to Reproduce**: Detailed steps to reproduce the issue
4. **Proof of Concept**: Code or screenshots demonstrating the vulnerability
5. **Suggested Fix**: If you have ideas for fixing the issue

### Response Timeline

- **Initial Response**: Within 24 hours
- **Investigation**: Within 48 hours
- **Fix Development**: Within 7 days for critical issues
- **Release**: As soon as possible after fix is ready

### Responsible Disclosure

We kindly ask that you:

- Do not publicly disclose the vulnerability until we have had a chance to address it
- Do not exploit the vulnerability beyond what is necessary to demonstrate it
- Do not access or modify data that does not belong to you

### Security Measures

This package implements several security measures:

1. **Input Validation**: All user inputs are validated and sanitized
2. **SQL Injection Protection**: Uses Laravel's query builder and parameter binding
3. **Mass Assignment Protection**: Follows Laravel's fillable/guarded patterns
4. **Authorization**: Supports Laravel's authorization mechanisms

### Common Vulnerabilities

We are particularly interested in reports about:

- SQL Injection vulnerabilities
- Mass assignment vulnerabilities
- Authorization bypass issues
- Information disclosure
- Cross-site scripting (XSS) in generated content

### Security Best Practices

When using this package:

1. **Validate Input**: Always validate and sanitize user input
2. **Use Fillable**: Define fillable attributes on your models
3. **Implement Authorization**: Use Laravel's policies and gates
4. **Keep Updated**: Regularly update the package to get security fixes
5. **Review Code**: Review your repository implementations for security issues

Thank you for helping keep Litepie Repository secure!
