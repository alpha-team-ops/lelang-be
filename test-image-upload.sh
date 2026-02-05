#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="http://localhost:8000/api/v1"
TOKEN="$1"

if [ -z "$TOKEN" ]; then
    echo -e "${RED}Error: Token not provided${NC}"
    echo "Usage: ./test-image-upload.sh <token>"
    exit 1
fi

# Create a test image
echo -e "${YELLOW}Creating test image...${NC}"
convert -size 200x200 xc:blue test-image.png 2>/dev/null || {
    # Fallback if ImageMagick not available - use Python
    python3 << 'PYEOF'
from PIL import Image
img = Image.new('RGB', (200, 200), color='blue')
img.save('test-image.png')
PYEOF
} || {
    # Last resort - create a minimal PNG using base64
    echo "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==" | base64 -d > test-image.png
}

# Test 1: Single Image Upload
echo -e "\n${YELLOW}Test 1: Single Image Upload${NC}"
curl -X POST "$BASE_URL/images/upload" \
  -H "Authorization: Bearer $TOKEN" \
  -F "image=@test-image.png" \
  -s | jq '.'

# Test 2: Get Image URL
echo -e "\n${YELLOW}Test 2: Get Image URL${NC}"
curl -X GET "$BASE_URL/images/url/auctions/test-image.png" \
  -H "Authorization: Bearer $TOKEN" \
  -s | jq '.'

# Cleanup
rm -f test-image.png

echo -e "\n${GREEN}Tests completed!${NC}"
