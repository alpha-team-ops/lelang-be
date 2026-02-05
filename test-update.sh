#!/bin/bash

# Test update LIVE auction dengan ISO 8601 datetime
AUCTION_ID="09074c5e-7345-446d-b0b6-8df4e6833bc9"
TOKEN="ba5780c0-19d7-433a-9386-861248e797e7"

echo "✅ Using admin token: $TOKEN"
echo ""

# Test update dengan ISO 8601 datetime
echo "Testing update LIVE auction with ISO 8601 datetime..."
curl -X PUT http://localhost:8000/api/v1/admin/auctions/$AUCTION_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Title Test",
    "end_time": "2026-02-10T10:30:00Z"
  }' 2>/dev/null | jq .

