# Auction Status Display Guide

## Overview
Sistem lelang memiliki berbagai status untuk auction yang ditampilkan di endpoint yang berbeda. Panduan ini menjelaskan bagaimana setiap endpoint menampilkan auction berdasarkan statusnya.

---

## Auction Status Definition

Auction dapat memiliki status berikut:

| Status | Description | Duration | Visibility |
|--------|-------------|----------|------------|
| **DRAFT** | Auction baru, belum scheduled | Indefinite | Admin only |
| **SCHEDULED** | Sudah dijadwalkan, tapi belum start | Hingga start time | Admin only |
| **LIVE** | Sedang berlangsung, active bidding | Start time → End time | ✅ **Portal (Display)** |
| **ENDING** | Opsional, dipakai untuk countdown final | End time - X minutes | ❌ Portal tidak menampilkan |
| **ENDED** | Auction selesai | Setelah end time | Admin only |
| **CANCELLED** | Dibatalkan | - | Admin only |

---

## Portal API - Display Rules

### Portal User Visibility

Portal user hanya melihat auction dengan status **LIVE**.

**Endpoints yang menampilkan auction di portal:**

#### 1. List Portal Auctions
```
GET /api/v1/auctions/portal/list
```
- ✅ Menampilkan: Status = **LIVE** saja
- ❌ Tidak menampilkan: ENDING, DRAFT, SCHEDULED, ENDED, CANCELLED

**Filter Implementation:**
```php
$query = Auction::where('status', 'LIVE');
```

#### 2. Get Single Portal Auction
```
GET /api/v1/auctions/portal/{id}
```
- ✅ Menampilkan: Status = **LIVE** saja
- ❌ Return 404 jika status ≠ LIVE

**Filter Implementation:**
```php
$auction = Auction::where('id', $id)
    ->where('status', 'LIVE')
    ->first();
```

#### 3. Search Auctions (Portal)
```
GET /api/v1/auctions/search
```
- ✅ Menampilkan: Status = **LIVE** saja
- Filter: Title, description, category

**Filter Implementation:**
```php
$query = Auction::where('status', 'LIVE');
```

#### 4. Get Auctions by Category (Portal)
```
GET /api/v1/auctions/category/{category}
```
- ✅ Menampilkan: Status = **LIVE** saja
- Filter: Category only

**Filter Implementation:**
```php
$query = Auction::where('status', 'LIVE')
    ->where('category', $category);
```

---

## Admin API - Display Rules

### Admin User Visibility

Admin dapat melihat auction dengan berbagai status sesuai permission dan endpoint.

#### 1. Get All Admin Auctions
```
GET /api/v1/auctions
```
- ✅ Menampilkan: Semua status sesuai filter
- Filter: status, category, page, limit, sort
- **Permission:** manage_auctions

#### 2. Get Auctions by Status (Admin)
```
GET /api/v1/auctions/status/{status}
```
- ✅ Menampilkan: Status spesifik yang diminta
- Valid statuses: DRAFT, SCHEDULED, LIVE, ENDING, ENDED, CANCELLED
- **Permission:** manage_auctions

**Filter Implementation:**
```php
$query = Auction::where('status', $status);
```

---

## Bidding Rules

### Bid Placement Rules

User dapat menempatkan bid pada auction dengan status:
- ✅ **LIVE** - Bidding aktif
- ✅ **ENDING** - Bidding masih bisa, countdown final

Bidding TIDAK bisa dilakukan pada:
- ❌ DRAFT, SCHEDULED, ENDED, CANCELLED

**Bid Controller Logic:**
```php
if (!in_array($auctionStatus, ['LIVE', 'ENDING'])) {
    throw ValidationException::withMessages([
        'auctionId' => 'Cannot bid on non-LIVE auction'
    ]);
}
```

---

## Key Points

1. **Portal Users**
   - Hanya melihat auction status **LIVE**
   - Tidak bisa melihat auction ENDING atau status lain
   - Dapat bid pada auction LIVE

2. **Admin Users**
   - Dapat melihat semua status melalui `/api/v1/auctions`
   - Dapat filter berdasarkan status tertentu
   - Dapat melihat statistik auction di semua tahap

3. **Data Consistency**
   - Portal list hanya mengambil data LIVE = User lihat akurat
   - Admin endpoint terpisah untuk manajemen penuh
   - No "ENDING" dalam portal response = User tidak bingung

---

## Configuration

Jika ingin mengubah display rules, update di location berikut:

**Portal List - LIVE filter:**
- File: `app/Http/Controllers/Api/V1/AuctionController.php`
- Method: `portalList()` - Line 407
- Change: `->where('status', 'LIVE')`

**Portal Show - LIVE filter:**
- File: `app/Http/Controllers/Api/V1/AuctionController.php`
- Method: `portalShow()` - Line 453
- Change: `->where('status', 'LIVE')`

**Bidding Allowed Status:**
- File: `app/Http/Controllers/Api/V1/BidController.php`
- Method: `place()` - Line 143
- Change: `['LIVE', 'ENDING']`

---

## Notes

- Status transition dari LIVE → ENDING adalah optional (admin dapat skip ENDING)
- ENDING digunakan hanya jika ada countdown/rush hour promotion
- Portal tidak menampilkan ENDING untuk kesederhanaan UI/UX
- Jika auction di-end lebih cepat, langsung jadi ENDED
