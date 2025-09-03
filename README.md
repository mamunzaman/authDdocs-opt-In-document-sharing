# AuthDocs – Opt-In Document Sharing

A secure WordPress plugin that allows sharing of document data (PDFs, Word files) with opt-in authentication.

## Features

### Core Functionality

- **Custom Post Type**: Create and manage documents through WordPress admin
- **Media Library Integration**: Use native WordPress media uploader for file uploads
- **Shortcode Generation**: Automatic shortcode creation for each document
- **Responsive Design**: 100% responsive fluid grid display
- **Secure Access Control**: Optional opt-in authentication for restricted documents

### Document Management

- Upload PDF, Word documents, and other file types
- Set documents as restricted (requires opt-in) or public (direct download)
- Generate shortcodes: `[authdocs id="123" restricted="yes"]`
- Manage document settings through WordPress admin

### Access Control

- **Public Documents**: Direct viewing without authentication
- **Restricted Documents**: Require opt-in form with name and email
- **Secure Link Generation**: Unique, hash-based viewing links
- **PDF Display**: PDFs display directly in browser instead of downloading
- **Request Management**: Admin interface to approve/decline access requests

### Admin Features

- **Document Requests Management**: View and manage all access requests
- **Always-Available Actions**: Accept, Decline, and Deactivate buttons always visible for any status
- **File Link Display**: View the actual document file links in the requests table
- **Unique Request Access**: Each request gets a unique, request-specific download link
- **Secure Link Generation**: Automatic secure link creation for approved requests
- **Access Revocation**: Revoke access even after approval, invalidating download links
- **Request History**: Track all requests with timestamps and status
- **Email Template Settings**: Customize email notifications with dynamic variables

## Installation

1. Upload the plugin files to `/wp-content/plugins/authdocs-opt-in-document-sharing/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin will automatically create necessary database tables

## Usage

### Creating Documents

1. Go to **Documents** in your WordPress admin
2. Click **Add New Document**
3. Enter a title and description
4. Upload a document file using the **Select Document** button
5. Check **Require Opt-in** if you want to restrict access
6. Publish the document
7. Copy the generated shortcode and use it on any page or post

### Managing Requests

1. Go to **Documents > Requests** in your WordPress admin
2. View all access requests with document file links
3. Use the always-available action buttons to:
   - **Accept/Re-accept**: Approve the request and generate a unique secure download link
   - **Decline/Re-decline**: Reject the request
   - **Deactivate/Keep Inactive**: Deactivate an approved request
   - **Copy Link**: Copy the unique secure download link for approved requests

**Note**: All action buttons are always visible, allowing you to change request status at any time. Each request gets a unique download link tied to that specific request ID.

### Email Template Settings

1. Go to **Documents > Settings** in your WordPress admin
2. Configure the email subject and HTML body for access granted notifications
3. Use dynamic variables in your templates:
   - `{{name}}` - Requester's name
   - `{{email}}` - Requester's email address
   - `{{link}}` - Generated secure download/view link
4. Preview your email template with sample data
5. Send test emails to verify your configuration
6. Save your template settings

**Note**: If a variable is missing (e.g., no name provided), it will be replaced with an empty string rather than showing placeholder text.

### Recipient Email Addresses

1. In **Documents > Settings**, configure recipient email addresses for admin notifications
2. Enter multiple email addresses separated by commas or semicolons
3. Invalid email addresses are automatically filtered out
4. If left empty, notifications are sent to the site admin email
5. All recipients receive notifications when document access is requested

### Autoresponder Email Template

1. Enable the autoresponder feature with a toggle switch
2. Configure the autoresponder subject and HTML body
3. Use dynamic variables in your templates:
   - `{name}` - Requester's name
   - `{email}` - Requester's email address
   - `{document_title}` - Title of the requested document
   - `{site_name}` - Name of your website
4. Send test autoresponder emails to verify your configuration
5. When enabled, users automatically receive confirmation emails upon request submission

**Note**: Unknown placeholders remain unchanged in the email, allowing for custom variable usage.

### Frontend Display

The shortcode renders a responsive document card with:

- Document title and description
- Request Access button (for restricted documents)
- Direct Download button (for public documents)

### User Experience

**For Restricted Documents:**

1. User clicks "Request Access"
2. Popup form appears requesting name and email
3. User submits the form
4. Admin receives the request in the admin panel
5. Admin approves the request
6. User receives a secure viewing link

**For Public Documents:**

1. User sees the document card
2. User clicks "Download Document"
3. File opens in browser (PDFs display inline, other files may download based on browser settings)

## Security Features

- **CSRF Protection**: All forms use WordPress nonces
- **Input Sanitization**: All user inputs are sanitized and validated
- **Secure Hash Generation**: Unique hashes for each approved request with request ID, email, document ID, timestamp, and WordPress salt
- **Email Validation**: Download links are tied to specific email addresses
- **Permission Checks**: Proper capability checks for admin functions
- **Direct Access Protection**: Blocks direct file access without valid authorization
- **Request-Specific Access**: Each request gets a unique hash tied to its specific ID
- **Media File Protection**: Prevents direct access to uploaded document files
- **Audit Logging**: Logs all document access attempts for security monitoring

## File Structure

```
authdocs-opt-in-document-sharing/
├── authdocs-opt-in-document-sharing.php  # Main plugin file
├── includes/
│   ├── Plugin.php                        # Main plugin class
│   ├── CustomPostType.php               # Document post type
│   ├── Shortcode.php                    # Shortcode handling
│   ├── Database.php                     # Database operations
│   └── Admin.php                        # Admin functionality
├── templates/
│   └── admin/
│       └── requests-page.php            # Admin requests page
├── assets/
│   ├── css/
│   │   ├── frontend.css                 # Frontend styles
│   │   └── admin.css                    # Admin styles
│   └── js/
│       ├── frontend.js                  # Frontend JavaScript
│       └── admin.js                     # Admin JavaScript
├── languages/
│   └── authdocs.pot                     # Translation template
└── README.md                            # This file
```

## Database Tables

The plugin creates one custom table:

- `wp_authdocs_requests`: Stores access requests and secure links

## Hooks and Filters

The plugin uses WordPress hooks for extensibility:

- `init`: Plugin initialization
- `admin_menu`: Admin menu registration
- `wp_enqueue_scripts`: Frontend asset loading
- `admin_enqueue_scripts`: Admin asset loading
- AJAX actions for request handling

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Internationalization

The plugin is fully internationalized and includes:

- Translation-ready strings using `__()` and `_e()`
- POT file for translators
- Text domain: `authdocs`

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### Version 1.0.0

- Initial release
- Custom post type for documents
- Shortcode generation
- Opt-in authentication system
- Admin request management
- Secure link generation
- Responsive design
- Internationalization support
