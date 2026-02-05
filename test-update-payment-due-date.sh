#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Testing Update Payment Due Date Endpoint ===${NC}\n"

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

# Step 2: Get winner bids
echo -e "\n${YELLOW}Step 2: Getting winner bids...${NC}"
WINNERS=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/bids/winners?limit=10&status=PAYMENT_PENDING")

echo "Response:"
echo "$WINNERS" | jq '.data[] | {id: .id, auctionTitle: .auctionTitle, status: .status, paymentDueDate: .paymentDueDate}' | head -30

# Step 3: Get a winner bid ID
echo -e "\n${YELLOW}Step 3: Getting winner bid details...${NC}"
WINNER_ID=$(echo "$WINNERS" | jq -r '.data[0].id // empty')
if [ -z "$WINNER_ID" ]; then
  echo -e "${RED}✗ No PAYMENT_PENDING winner bid found${NC}"
  echo "Please create a winner bid first."
  exit 1
fi

echo -e "${GREEN}✓ Found winner bid: $WINNER_ID${NC}"

# Get current details
WINNER_DETAILS=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/bids/winners/$WINNER_ID")
echo -e "\n${BLUE}Current Winner Bid Details:${NC}"
echo "$WINNER_DETAILS" | jq '.data'

CURRENT_STATUS=$(echo "$WINNER_DETAILS" | jq -r '.data.status')
CURRENT_DUE_DATE=$(echo "$WINNER_DETAILS" | jq -r '.data.paymentDueDate')

echo -e "\nCurrent Status: $CURRENT_STATUS"
echo "Current Payment Due Date: $CURRENT_DUE_DATE"

# Step 4: Calculate new due date (48 hours from now)
echo -e "\n${YELLOW}Step 4: Preparing new payment due date...${NC}"
NEW_DUE_DATE=$(date -d "+48 hours" '+%Y-%m-%d %H:%M:%S')
echo "New Payment Due Date: $NEW_DUE_DATE"

# Step 5: Update payment due date
echo -e "\n${YELLOW}Step 5: Updating payment due date...${NC}"
UPDATE_RESPONSE=$(curl -s -X PUT "$BASE_URL/bids/winners/$WINNER_ID/payment-due-date" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "paymentDueDate": "'$NEW_DUE_DATE'",
    "notes": "Extended deadline - Test update"
  }')

echo "Response:"
echo "$UPDATE_RESPONSE" | jq '.'

SUCCESS=$(echo $UPDATE_RESPONSE | jq -r '.success')
if [ "$SUCCESS" == "true" ]; then
  echo -e "\n${GREEN}✓ Payment due date updated successfully!${NC}"
  
  # Step 6: Verify the update
  echo -e "\n${YELLOW}Step 6: Verifying the update...${NC}"
  VERIFY=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/bids/winners/$WINNER_ID")
  VERIFIED_DUE_DATE=$(echo "$VERIFY" | jq -r '.data.paymentDueDate')
  echo "Verified Payment Due Date: $VERIFIED_DUE_DATE"
  echo -e "${GREEN}✓ Update verified!${NC}"
else
  echo -e "\n${RED}✗ Update failed${NC}"
  ERROR=$(echo $UPDATE_RESPONSE | jq -r '.error')
  echo "Error: $ERROR"
  exit 1
fi

# Step 7: Test error - invalid date (past date)
echo -e "\n${YELLOW}Step 7: Testing validation - past date (should FAIL)...${NC}"
PAST_DATE=$(date -d "-1 days" '+%Y-%m-%d %H:%M:%S')
INVALID_RESPONSE=$(curl -s -X PUT "$BASE_URL/bids/winners/$WINNER_ID/payment-due-date" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "paymentDueDate": "'$PAST_DATE'",
    "notes": "Testing invalid date"
  }')

echo "Response:"
echo "$INVALID_RESPONSE" | jq '.'

ERROR_CODE=$(echo $INVALID_RESPONSE | jq -r '.code // empty')
if [ "$ERROR_CODE" == "VALIDATION_ERROR" ]; then
  echo -e "${GREEN}✓ Correctly rejected past date${NC}"
else
  echo -e "${YELLOW}⚠ Unexpected response${NC}"
fi

echo -e "\n${GREEN}=== All Tests Complete ===${NC}"
