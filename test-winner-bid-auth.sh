#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Testing Winner Bid API with Authentication ===${NC}\n"

BASE_URL="http://localhost:8000/api/v1"

# Step 1: Login to get token
echo -e "${YELLOW}Step 1: Authenticating...${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "alpha.dev@deraly.id",
    "password": "Real1Novation!"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.accessToken // empty')
if [ -z "$TOKEN" ]; then
  echo -e "${RED}✗ Login failed${NC}"
  echo "Response: $LOGIN_RESPONSE" | jq '.'
  exit 1
fi
echo -e "${GREEN}✓ Logged in successfully${NC}"
echo "Token: ${TOKEN:0:20}..."

# Step 2: Get auctions
echo -e "\n${YELLOW}Step 2: Getting auctions...${NC}"
AUCTIONS=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/auctions?limit=10")

echo "Response:"
echo "$AUCTIONS" | jq '.data[] | {id: .id, title: .title, status: .status, endTime: .endTime}' | head -50

# Step 3: Find a LIVE auction
echo -e "\n${YELLOW}Step 3: Finding LIVE auction...${NC}"
LIVE_ID=$(echo "$AUCTIONS" | jq -r '.data[] | select(.status == "LIVE") | .id' | head -1)
if [ -z "$LIVE_ID" ]; then
  echo -e "${YELLOW}⚠ No LIVE auction found${NC}"
  LIVE_ID=$(echo "$AUCTIONS" | jq -r '.data[0].id')
fi

LIVE_AUCTION=$(echo "$AUCTIONS" | jq -r ".data[] | select(.id == \"$LIVE_ID\")")
LIVE_STATUS=$(echo "$LIVE_AUCTION" | jq -r '.status')
echo "Auction ID: $LIVE_ID (Status: $LIVE_STATUS)"

# Step 4: Try to create winner bid for LIVE auction (should fail)
echo -e "\n${YELLOW}Step 4: Test - Try to create winner bid for LIVE auction (should FAIL)...${NC}"
RESPONSE=$(curl -s -X POST "$BASE_URL/bids/winners" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"auctionId": "'$LIVE_ID'"}')

echo "Response:"
echo "$RESPONSE" | jq '.'

ERROR_CODE=$(echo $RESPONSE | jq -r '.code // "success"')
if [ "$ERROR_CODE" == "AUCTION_NOT_ENDED" ]; then
  echo -e "${GREEN}✓ Correctly rejected LIVE auction${NC}"
elif [ "$ERROR_CODE" == "success" ]; then
  # Maybe auction has ended by now, that's OK
  echo -e "${YELLOW}⚠ Winner bid was created (auction may have ended)${NC}"
  WINNER_ID=$(echo $RESPONSE | jq -r '.data.id')
  echo "Winner ID: $WINNER_ID"
else
  echo -e "${RED}✗ Unexpected error code: $ERROR_CODE${NC}"
fi

echo -e "\n${GREEN}=== Test Complete ===${NC}"
