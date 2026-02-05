#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Testing Winner Bid API ===${NC}\n"

BASE_URL="http://localhost:8000/api/v1"
ORG_CODE="ORG-DERALY-001"

# Get the ENDING auction (should be endable)
echo -e "${YELLOW}1. Getting ENDING auction...${NC}"
AUCTION_ID=$(curl -s "$BASE_URL/auctions?status=ENDING&limit=1" | jq -r '.data[0].id')
echo "Auction ID: $AUCTION_ID"
if [ -z "$AUCTION_ID" ] || [ "$AUCTION_ID" == "null" ]; then
  echo -e "${RED}No ENDING auction found${NC}\n"
  exit 1
fi

AUCTION_DATA=$(curl -s "$BASE_URL/auctions/$AUCTION_ID")
AUCTION_STATUS=$(echo $AUCTION_DATA | jq -r '.data.status')
AUCTION_END_TIME=$(echo $AUCTION_DATA | jq -r '.data.endTime')
CURRENT_TIME=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

echo "Auction Status: $AUCTION_STATUS"
echo "Auction End Time: $AUCTION_END_TIME"
echo "Current Time: $CURRENT_TIME"

# Test 1: Try to create winner bid for LIVE auction (should fail)
echo -e "\n${YELLOW}2. Test: Create winner bid for LIVE auction (should FAIL)...${NC}"
LIVE_AUCTION=$(curl -s "$BASE_URL/auctions?status=LIVE&limit=1" | jq -r '.data[0]')
LIVE_ID=$(echo $LIVE_AUCTION | jq -r '.id')
LIVE_STATUS=$(echo $LIVE_AUCTION | jq -r '.status')

echo "Live Auction ID: $LIVE_ID (Status: $LIVE_STATUS)"

RESPONSE=$(curl -s -X POST "$BASE_URL/bids/winners" \
  -H "Content-Type: application/json" \
  -d '{"auctionId": "'$LIVE_ID'"}')

ERROR_CODE=$(echo $RESPONSE | jq -r '.code // "success"')
if [ "$ERROR_CODE" == "AUCTION_NOT_ENDED" ]; then
  echo -e "${GREEN}✓ Correctly rejected LIVE auction${NC}"
  echo "Error: $(echo $RESPONSE | jq -r '.error')"
else
  echo -e "${RED}✗ Should have rejected LIVE auction${NC}"
  echo "Response: $RESPONSE"
fi

# Test 2: Check if ENDING auction has ended yet
echo -e "\n${YELLOW}3. Checking if ENDING auction has actually ended...${NC}"
echo "Comparing: End Time ($AUCTION_END_TIME) vs Current ($CURRENT_TIME)"

# Try to create winner bid
RESPONSE=$(curl -s -X POST "$BASE_URL/bids/winners" \
  -H "Content-Type: application/json" \
  -d '{"auctionId": "'$AUCTION_ID'"}')

echo "Response: $RESPONSE" | jq '.'

SUCCESS=$(echo $RESPONSE | jq -r '.success')
if [ "$SUCCESS" == "true" ]; then
  echo -e "${GREEN}✓ Winner bid created successfully${NC}"
  WINNER_ID=$(echo $RESPONSE | jq -r '.data.id')
  echo "Winner Bid ID: $WINNER_ID"
  
  # Test 3: Try to create duplicate winner bid (should fail)
  echo -e "\n${YELLOW}4. Test: Create duplicate winner bid (should FAIL)...${NC}"
  RESPONSE=$(curl -s -X POST "$BASE_URL/bids/winners" \
    -H "Content-Type: application/json" \
    -d '{"auctionId": "'$AUCTION_ID'"}')
  
  ERROR_CODE=$(echo $RESPONSE | jq -r '.code // "success"')
  if [ "$ERROR_CODE" == "WINNER_ALREADY_EXISTS" ]; then
    echo -e "${GREEN}✓ Correctly rejected duplicate winner bid${NC}"
  else
    echo -e "${RED}✗ Should have rejected duplicate${NC}"
  fi
else
  ERROR=$(echo $RESPONSE | jq -r '.error')
  CODE=$(echo $RESPONSE | jq -r '.code')
  echo -e "${YELLOW}⚠ Cannot create winner bid: $ERROR ($CODE)${NC}"
  echo "This is expected if auction hasn't ended yet"
fi

echo -e "\n${GREEN}=== Test Complete ===${NC}"
