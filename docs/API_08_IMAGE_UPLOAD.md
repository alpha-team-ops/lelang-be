# Image Upload API Documentation

## Overview
The Image Upload API handles storage and management of auction images using MinIO S3-compatible object storage. All images are stored in the `lelang` bucket under the `auctions/` folder.

## Base URL
```
/api/v1/images
```

## Authentication
All endpoints require JWT Bearer token in the `Authorization` header with `manage_auctions` permission.

```
Authorization: Bearer <JWT_TOKEN>
```

---

## Endpoints

### 1. Upload Single Image
**POST** `/api/v1/images/upload`

Upload a single image file to MinIO storage.

#### Request
- **Content-Type**: `multipart/form-data`
- **Field**: `file` (required)
  - Type: File
  - Formats: JPEG, PNG, GIF, WebP
  - Max Size: 5MB (5120 KB)

#### Example
```bash
curl -X POST http://localhost:8000/api/v1/images/upload \
  -H "Authorization: Bearer <TOKEN>" \
  -F "file=@/path/to/image.png"
```

#### Success Response (201)
```json
{
  "success": true,
  "message": "Image uploaded successfully",
  "data": {
    "filename": "15efe33d-1a62-4f0e-8038-8f12941f3b31.png",
    "path": "auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png",
    "url": "http://localhost:9000/lelang/auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png",
    "size": 910,
    "mime_type": "image/png"
  }
}
```

#### Error Response (422)
```json
{
  "success": false,
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {
    "file": [
      "The file field is required.",
      "The file must be an image.",
      "The file must be a file of type: jpeg, png, jpg, gif, webp.",
      "The file may not be greater than 5120 kilobytes."
    ]
  }
}
```

---

### 2. Bulk Upload Images
**POST** `/api/v1/images/bulk-upload`

Upload multiple image files in a single request (max 10 files).

#### Request
- **Content-Type**: `multipart/form-data`
- **Field**: `files[0]`, `files[1]`, ..., `files[n]` (required, indexed array)
  - **IMPORTANT**: Use indexed field names, NOT duplicate field names
  - Max Files: 10
  - Formats: JPEG, PNG, GIF, WebP
  - Max Size per File: 5MB (5120 KB)

#### Example

**✅ CORRECT - Using indexed field names**:
```bash
curl -X POST http://localhost:8000/api/v1/images/bulk-upload \
  -H "Authorization: Bearer <TOKEN>" \
  -F "files[0]=@image1.png" \
  -F "files[1]=@image2.jpg" \
  -F "files[2]=@image3.gif"
```

**JavaScript/Axios (CORRECT)**:
```javascript
const files = [file1, file2, file3]; // from file input
const formData = new FormData();

// Append with indexed field names
files.forEach((file, index) => {
  formData.append(`files[${index}]`, file);
});

await axios.post('/api/v1/images/bulk-upload', formData, {
  headers: {
    'Content-Type': 'multipart/form-data',
    'Authorization': `Bearer ${token}`
  }
});
```

**❌ WRONG - Do NOT use duplicate field names**:
```bash
# This will only upload the LAST file!
curl -X POST http://localhost:8000/api/v1/images/bulk-upload \
  -H "Authorization: Bearer <TOKEN>" \
  -F "files=@image1.png" \
  -F "files=@image2.jpg"
```

```javascript
// This will ONLY upload the last file!
const formData = new FormData();
formData.append('files', file1);
formData.append('files', file2); // Overwrites file1!
```

#### Success Response (201)
```json
{
  "success": true,
  "message": "Images uploaded successfully",
  "data": {
    "images": [
      {
        "filename": "ff1afd41-4f2f-48a7-adf5-fbba8c8dc10d.png",
        "path": "auctions/ff1afd41-4f2f-48a7-adf5-fbba8c8dc10d.png",
        "url": "http://localhost:9000/lelang/auctions/ff1afd41-4f2f-48a7-adf5-fbba8c8dc10d.png",
        "size": 587,
        "mime_type": "image/png"
      },
      {
        "filename": "a7b2cd45-8901-4f5e-b2c1-23456789abcd.jpg",
        "path": "auctions/a7b2cd45-8901-4f5e-b2c1-23456789abcd.jpg",
        "url": "http://localhost:9000/lelang/auctions/a7b2cd45-8901-4f5e-b2c1-23456789abcd.jpg",
        "size": 2048,
        "mime_type": "image/jpeg"
      }
    ],
    "count": 2,
    "errors": null
  }
}
```

#### Partial Failure Response (201 - some files succeeded)
```json
{
  "success": false,
  "message": "Images uploaded successfully",
  "data": {
    "images": [
      {
        "filename": "ff1afd41-4f2f-48a7-adf5-fbba8c8dc10d.png",
        "path": "auctions/ff1afd41-4f2f-48a7-adf5-fbba8c8dc10d.png",
        "url": "http://localhost:9000/lelang/auctions/ff1afd41-4f2f-48a7-adf5-fbba8c8dc10d.png",
        "size": 587,
        "mime_type": "image/png"
      }
    ],
    "count": 1,
    "errors": {
      "1": "File size exceeds 5MB limit",
      "2": "Invalid image format. Allowed: jpeg, png, gif, webp"
    }
  }
}
```

#### Error Response (422 - validation error)
```json
{
  "success": false,
  "error": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {
    "files": [
      "At least one file is required"
    ]
  }
}
```

---

### 3. Get Image URL
**GET** `/api/v1/images/url/{path}`

Retrieve the public URL for an uploaded image. This endpoint does NOT require authentication and can be used to access images publicly.

#### Parameters
- **path** (required, string): The image path returned from upload endpoints
  - Example: `auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png`

#### Example
```bash
curl -X GET "http://localhost:8000/api/v1/images/url/auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png" \
  -H "Authorization: Bearer <TOKEN>"
```

#### Success Response (200)
```json
{
  "success": true,
  "data": {
    "path": "auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png",
    "url": "http://localhost:9000/lelang/auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png"
  }
}
```

#### Error Response (404)
```json
{
  "success": false,
  "error": "Image not found",
  "code": "IMAGE_NOT_FOUND"
}
```

---

### 4. Delete Image
**DELETE** `/api/v1/images/{path}`

Remove an image from MinIO storage.

#### Parameters
- **path** (required, string): The image path returned from upload endpoints
  - Example: `auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png`

#### Example
```bash
curl -X DELETE "http://localhost:8000/api/v1/images/auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png" \
  -H "Authorization: Bearer <TOKEN>"
```

#### Success Response (200)
```json
{
  "success": true,
  "message": "Image deleted successfully"
}
```

#### Error Response (404)
```json
{
  "success": false,
  "error": "Image not found",
  "code": "IMAGE_NOT_FOUND"
}
```

---

## Image Storage Details

### Storage Location
- **Bucket**: `lelang`
- **Folder**: `auctions/`
- **Naming**: UUID v4 format with original file extension

Example: `auctions/15efe33d-1a62-4f0e-8038-8f12941f3b31.png`

### Access
- **Endpoint**: MinIO API endpoint `http://localhost:9000`
- **Console**: MinIO console `http://localhost:9001`
- **Public Access**: Images are stored with public ACL and can be accessed directly via the returned URL

### Configuration
```php
// config/filesystems.php
'minio' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => 'us-east-1',
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => true,
]
```

---

## Integration with Auctions

### Workflow
1. **Upload images** → `/api/v1/images/upload`
2. **Store image paths** → Save `path` field in auction record
3. **Retrieve image URLs** → Use `path` with `/api/v1/images/url/{path}` endpoint
4. **Display images** → Use returned `url` in frontend

### Auction Model Integration (Future)
Consider adding `images` relationship to Auction model:

```php
// app/Models/Auction.php
public function images()
{
    return $this->hasMany(AuctionImage::class);
}
```

### Database Schema (Future)
```sql
CREATE TABLE auction_images (
    id UUID PRIMARY KEY,
    auction_id UUID NOT NULL REFERENCES auctions(id) ON DELETE CASCADE,
    path VARCHAR(255) NOT NULL,
    url VARCHAR(255),
    filename VARCHAR(255),
    size BIGINT,
    mime_type VARCHAR(50),
    display_order INT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Validation failed (file format, size, etc.) |
| `UPLOAD_ERROR` | 500 | File upload to storage failed |
| `IMAGE_NOT_FOUND` | 404 | Image does not exist in storage |
| `UNAUTHORIZED` | 401 | Missing or invalid authentication token |
| `FORBIDDEN` | 403 | User lacks `manage_auctions` permission |

---

## Testing Checklist

### Single Upload
- [x] Upload valid PNG image (< 5MB)
- [x] Upload valid JPEG image
- [x] Verify UUID filename generation
- [x] Verify public URL is returned
- [x] Verify image accessible at returned URL
- [ ] Test with oversized file (> 5MB)
- [ ] Test with invalid format (e.g., .txt)
- [ ] Test without file field
- [ ] Test with missing authentication token
- [ ] Test with insufficient permissions

### Bulk Upload
- [x] Upload 2 PNG images
- [ ] Upload maximum 10 files
- [ ] Verify all files have unique filenames
- [ ] Verify partial success handling (some files fail)
- [ ] Test exceeding 10 file limit
- [ ] Test with empty request

### URL Retrieval
- [x] Get URL for existing image
- [ ] Get URL for non-existent path
- [ ] Verify URL points to MinIO endpoint

### Image Deletion
- [x] Delete existing image
- [ ] Delete non-existent image
- [ ] Verify file removed from MinIO

### Integration
- [ ] Upload images when creating auction
- [ ] Update auction image list
- [ ] Delete images when auction is deleted
- [ ] Display images in auction details
- [ ] Handle missing images gracefully

---

## Performance Considerations

1. **Upload Size Limit**: 5MB per file to balance quality and performance
2. **Bulk Upload Limit**: 10 files maximum to prevent timeout
3. **Storage**: MinIO runs on localhost:9000 (local network)
4. **Cleanup**: Implement orphaned image cleanup when auctions are deleted

---

## Future Enhancements

1. **Image Resizing**: Generate thumbnails for preview
2. **Image Optimization**: Compress images on upload
3. **Image Validation**: Deep file type validation (not just MIME type)
4. **Batch Operations**: Delete multiple images in one request
5. **Image Metadata**: Extract and store EXIF data
6. **CDN Integration**: Cache images in CDN for faster delivery
7. **Audit Trail**: Track image upload history
