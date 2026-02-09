# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a PHP-based CIF (Corporate Identification Number) management system for managing client-CIF associations. It provides a web interface to add, edit, and delete CIFs associated with clients, with search and pagination capabilities.

## Architecture

### Database Schema

The application uses PostgreSQL with three main tables:

- **clients**: Stores client information
  - `id` (primary key)
  - `client` (client name)
  - `deleted` (soft delete flag)

- **cifs**: Stores CIF (tax identification) information
  - `id` (primary key)
  - `cif` (tax ID string)
  - `entidad` (company name/entity)

- **clients_cifs**: Many-to-many junction table
  - `client_id` (foreign key to clients)
  - `cif_id` (foreign key to cifs)

### File Structure

- **config.php**: Database connection setup and session initialization
  - Establishes PDO connection to PostgreSQL
  - Generates CSRF tokens for form security
  - Database credentials are stored here (not for production use)

- **cifs.php**: Main UI page
  - Displays paginated list of clients with their associated CIFs
  - Search functionality across client names, CIF numbers, and entity names
  - Modal-based forms for add/edit/delete operations
  - Uses jQuery 1.12.4 for DOM manipulation
  - Pagination supports 100, 200, 500, or "all" results per page
  - Excludes client ID 743 and soft-deleted clients from queries

- **action.php**: Backend action handler
  - Processes POST requests for add/edit/delete operations
  - CSRF protection on all POST requests
  - Transaction-based operations for data consistency
  - Debug mode: Shows detailed SQL execution info for delete operations
  - Logs operations to `/tmp/cif_debug.log`

## Key Architectural Patterns

### CIF Deletion Logic
When deleting a CIF association:
1. Removes the association from `clients_cifs` table
2. Checks if the CIF is associated with any other clients
3. If no associations remain, deletes the CIF from the `cifs` table
4. If associations remain, keeps the CIF record intact

This ensures CIFs are only deleted when no longer referenced by any client.

### Query Optimization
- Uses prepared statements with bound parameters for all queries
- Distinct client IDs are fetched first, then CIFs are loaded in a separate query
- Dynamic IN clause construction for batch CIF loading
- Search queries use ILIKE for case-insensitive matching across multiple columns

### CSRF Protection
All POST operations require a valid CSRF token that is:
- Generated at session start in config.php
- Included as a hidden field in all forms
- Validated in action.php before processing any action

## Development Notes

### Database Connection
The application connects to a PostgreSQL database. Connection parameters are configured via environment variables in config.php:
- DB_HOST: Database host
- DB_PORT: Database port (default: 5432)
- DB_NAME: Database name
- DB_USER: Database user
- DB_PASS: Database password

Environment variables are configured in the `.env` file (gitignored) or passed through Docker.

### BASE_PATH Configuration
The application supports running in a subdirectory via the `BASE_PATH` environment variable for database connection and session management.

**Important**: The application uses **relative URLs** for all form actions, redirects, and navigation to ensure compatibility with reverse proxies (like Nginx Proxy Manager). This prevents path duplication issues (e.g., `/cifs/cifs/`) that can occur when using absolute paths behind a reverse proxy.

All internal navigation uses relative paths:
- Form actions: `action="action.php"`
- Redirects: `Location: index.php`
- Pagination: `?page=2` (relative query strings)

### Debug Mode
action.php currently runs in debug mode for delete operations:
- Displays detailed HTML output showing SQL queries and parameters
- Logs operations to `/tmp/cif_debug.log`
- Shows whether CIF records were deleted or retained

To disable debug mode, modify the delete action in action.php to redirect to cifs.php instead of showing debug output.

### Frontend Dependencies
- jQuery 1.12.4 (loaded from CDN)
- No build process required
- Inline CSS with CSS custom properties for theming

### Testing Approach
Since this is a simple PHP application with no test framework:
- Test manually through the web interface
- Verify database state directly using psql or another PostgreSQL client
- Check `/tmp/cif_debug.log` for operation logs
- Test CSRF protection by attempting operations without valid tokens

## Docker Setup

### Commands

**Development** (with live code reloading via volumes):
```bash
docker-compose up -d          # Start containers
docker-compose logs -f        # View logs
docker-compose down           # Stop containers
```

**Production**:
```bash
docker-compose -f docker-compose.prod.yml up -d    # Start
docker-compose -f docker-compose.prod.yml down     # Stop
```

### Port Configuration
- The application runs on port **2020** (mapped to container port 80)
- Production mode connects to external network `npm-network` for reverse proxy integration

### Environment Files
- `.env` - Local environment variables (gitignored, create from `.env.example`)
- `.env.example` - Template for environment configuration

### Docker Files
- `Dockerfile` - PHP 8.2 with Apache and PostgreSQL PDO extension
- `docker-compose.yml` - Development configuration with volume mounts
- `docker-compose.prod.yml` - Production configuration without volumes
