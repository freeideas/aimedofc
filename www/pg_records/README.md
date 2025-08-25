# Medical Records Viewer

## Overview

This page displays the patient's medical records provided by the doctor's office. The interface shows a list of document titles on the left and a PDF viewer on the right for viewing the actual documents.

## Features

- **Document List** (left sidebar): Shows all available medical records with titles and dates
- **PDF Viewer** (right panel): Displays the selected PDF document using browser's native PDF rendering
- **Responsive Layout**: Adjusts for different screen sizes
- **Session Protection**: Only accessible to authenticated users

## Database Requirements

Uses the `medical_records` table from `aioffice.db`:
- `record_id`: Unique identifier
- `user_id`: Links to authenticated user
- `record_title`: Human-readable title shown in list
- `record_date`: Date of the record
- `source_filename`: PDF filename in data/uploads/

## File Structure

- `index.php` - Main page with authentication check and layout
- `get_pdf.php` - Secure PDF delivery endpoint
- `test.py` - Unit tests

## Security

- Requires valid session (`aiofc_session` cookie)
- PDFs served through PHP to prevent direct access
- User can only view their own records
- Path traversal protection on PDF filenames

## Layout

```
+------------------+--------------------------------+
| Document List    | PDF Viewer                     |
|                  |                                |
| ▸ Lab Results    | [PDF content displayed here]  |
|   2024-01-15     |                                |
|                  |                                |
| ▸ Prescription   |                                |
|   2024-01-10     |                                |
|                  |                                |
| ▸ Visit Notes    |                                |
|   2024-01-05     |                                |
+------------------+--------------------------------+
```

## User Flow

1. User accesses pg_records (must be logged in)
2. Page loads list of available documents
3. User clicks on a document title
4. PDF loads in the right panel via iframe
5. Browser's native PDF viewer handles display

## Technical Details

- Uses iframe with `src` pointing to `get_pdf.php?id=<record_id>`
- `get_pdf.php` validates session and ownership before serving PDF
- Content-Type header set to `application/pdf` for proper rendering
- Falls back to download if browser doesn't support inline PDF viewing