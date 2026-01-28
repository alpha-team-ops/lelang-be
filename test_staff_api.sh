#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

BASE_URL="http://localhost:8000/api/v1"

echo -e "${BLUE}════════════════════════════════════════${NC}"
echo -e "${BLUE}  Staff Management API - Complete Test${NC}"
echo -e "${BLUE}════════════════════════════════════════${NC}\n"

# Step 1: Login with alpha.dev
echo -e "${YELLOW}Step 1: Login with alpha.dev${NC}"
LOGIN=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "alpha.dev@mansurcorp.id",
    "password": "Alpha123!"
  }')

TOKEN=$(echo $LOGIN | jq -r '.data.accessToken')
ORG_CODE=$(echo $LOGIN | jq -r '.data.organizationCode')

echo "Token: ${TOKEN:0:50}..."
echo "Organization: $ORG_CODE"
echo ""

# Step 2: Get all staff (should have 1 - alpha.dev)
echo -e "${YELLOW}Step 2: Get all staff${NC}"
GET_ALL=$(curl -s -X GET "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN")
echo $GET_ALL | jq '.'
STAFF_COUNT=$(echo $GET_ALL | jq '.data | length')
echo "Current staff count: $STAFF_COUNT"
echo ""

# Step 3: Create new staff
echo -e "${YELLOW}Step 3: Create new staff (MODERATOR)${NC}"
CREATE=$(curl -s -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Moderator",
    "email": "john.mod@example.com",
    "password": "SecurePass123!",
    "role": "MODERATOR"
  }')
echo $CREATE | jq '.'
STAFF_ID=$(echo $CREATE | jq -r '.data.id')
echo "New staff ID: $STAFF_ID"
echo ""

# Step 4: Get staff by ID
echo -e "${YELLOW}Step 4: Get staff by ID${NC}"
GET_BY_ID=$(curl -s -X GET "$BASE_URL/staff/$STAFF_ID" \
  -H "Authorization: Bearer $TOKEN")
echo $GET_BY_ID | jq '.'
echo ""

# Step 5: Get all staff with pagination
echo -e "${YELLOW}Step 5: Get all staff with limit=10 and page=1${NC}"
GET_PAGINATED=$(curl -s -X GET "$BASE_URL/staff?limit=10&page=1" \
  -H "Authorization: Bearer $TOKEN")
echo $GET_PAGINATED | jq '.'
echo ""

# Step 6: Filter by role
echo -e "${YELLOW}Step 6: Filter staff by role=MODERATOR${NC}"
FILTER_ROLE=$(curl -s -X GET "$BASE_URL/staff?role=MODERATOR" \
  -H "Authorization: Bearer $TOKEN")
echo $FILTER_ROLE | jq '.'
echo ""

# Step 7: Search staff
echo -e "${YELLOW}Step 7: Search staff by name (john)${NC}"
SEARCH=$(curl -s -X GET "$BASE_URL/staff?search=john" \
  -H "Authorization: Bearer $TOKEN")
echo $SEARCH | jq '.'
echo ""

# Step 8: Update staff
echo -e "${YELLOW}Step 8: Update staff name${NC}"
UPDATE=$(curl -s -X PUT "$BASE_URL/staff/$STAFF_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Updated"
  }')
echo $UPDATE | jq '.'
echo ""

# Step 9: Update staff status
echo -e "${YELLOW}Step 9: Update staff status to INACTIVE${NC}"
UPDATE_STATUS=$(curl -s -X PUT "$BASE_URL/staff/$STAFF_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "INACTIVE"
  }')
echo $UPDATE_STATUS | jq '.'
echo ""

# Step 10: Update activity (last login)
echo -e "${YELLOW}Step 10: Update staff activity${NC}"
UPDATE_ACTIVITY=$(curl -s -X PUT "$BASE_URL/staff/$STAFF_ID/activity" \
  -H "Authorization: Bearer $TOKEN")
echo $UPDATE_ACTIVITY | jq '.'
echo ""

# Step 11: Create another staff for deletion test
echo -e "${YELLOW}Step 11: Create another staff (for deletion test)${NC}"
CREATE2=$(curl -s -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Temp",
    "email": "jane.temp@example.com",
    "password": "SecurePass123!",
    "role": "MODERATOR"
  }')
STAFF_ID_2=$(echo $CREATE2 | jq -r '.data.id')
echo "New staff ID: $STAFF_ID_2"
echo ""

# Step 12: Delete staff
echo -e "${YELLOW}Step 12: Delete staff ($STAFF_ID_2)${NC}"
DELETE=$(curl -s -X DELETE "$BASE_URL/staff/$STAFF_ID_2" \
  -H "Authorization: Bearer $TOKEN")
echo $DELETE | jq '.'
echo ""

# Step 13: Verify deletion - staff should not be found
echo -e "${YELLOW}Step 13: Verify deletion (get deleted staff - should fail)${NC}"
GET_DELETED=$(curl -s -X GET "$BASE_URL/staff/$STAFF_ID_2" \
  -H "Authorization: Bearer $TOKEN")
echo $GET_DELETED | jq '.'
echo ""

# Step 14: Error test - duplicate email
echo -e "${YELLOW}Step 14: Error test - duplicate email${NC}"
DUP_EMAIL=$(curl -s -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Another User",
    "email": "john.mod@example.com",
    "password": "SecurePass123!",
    "role": "MODERATOR"
  }')
echo $DUP_EMAIL | jq '.'
echo ""

# Step 15: Error test - invalid password
echo -e "${YELLOW}Step 15: Error test - invalid password (no special char)${NC}"
INVALID_PASS=$(curl -s -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "NoSpecialChar123",
    "role": "MODERATOR"
  }')
echo $INVALID_PASS | jq '.'
echo ""

# Step 16: Final staff count
echo -e "${YELLOW}Step 16: Final staff list${NC}"
FINAL_LIST=$(curl -s -X GET "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN")
echo $FINAL_LIST | jq '.'
echo ""

echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}  All Tests Complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
