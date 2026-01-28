#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

BASE_URL="http://localhost:8000/api/v1"

echo -e "${BLUE}════════════════════════════════════════${NC}"
echo -e "${BLUE}  Staff Management API Test Suite${NC}"
echo -e "${BLUE}════════════════════════════════════════${NC}\n"

# Login to get fresh token
echo -e "${YELLOW}Getting fresh authentication token...${NC}"
LOGIN=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "alpha.dev@mansurcorp.id",
    "password": "Alpha123!"
  }')

TOKEN=$(echo $LOGIN | jq -r '.data.accessToken')
echo "✓ Authenticated\n"

# Test 1: Get all staff
echo -e "${YELLOW}Test 1: Get all staff${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
COUNT=$(echo "$BODY" | jq '.data | length')
echo "Status: $STATUS | Staff count: $COUNT"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 2: Get staff by ID
echo -e "${YELLOW}Test 2: Get staff by ID${NC}"
STAFF_ID=$(echo "$BODY" | jq -r '.data[0].id')
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/staff/$STAFF_ID" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
EMAIL=$(echo "$RESP" | head -n -1 | jq -r '.data.email')
echo "Status: $STATUS | Email: $EMAIL"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 3: Pagination
echo -e "${YELLOW}Test 3: Pagination (limit=2, page=1)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/staff?limit=2&page=1" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | head -n -1)
TOTAL=$(echo "$BODY" | jq '.meta.pagination.total')
echo "Status: $STATUS | Total records: $TOTAL"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 4: Filter by role
echo -e "${YELLOW}Test 4: Filter by role=ADMIN${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/staff?role=ADMIN" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
COUNT=$(echo "$RESP" | head -n -1 | jq '.data | length')
echo "Status: $STATUS | ADMIN count: $COUNT"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 5: Search by email
echo -e "${YELLOW}Test 5: Search by email (john)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/staff?search=john" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
COUNT=$(echo "$RESP" | head -n -1 | jq '.data | length')
echo "Status: $STATUS | Found: $COUNT record(s)"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 6: Create staff with valid data
echo -e "${YELLOW}Test 6: Create new staff${NC}"
NEW_EMAIL="newstaff_$(date +%s)@example.com"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Staff",
    "email": "'$NEW_EMAIL'",
    "password": "SecurePass123!",
    "role": "MODERATOR"
  }')
STATUS=$(echo "$RESP" | tail -1)
NEW_ID=$(echo "$RESP" | head -n -1 | jq -r '.data.id')
echo "Status: $STATUS | New ID: ${NEW_ID:0:20}..."
[ "$STATUS" = "201" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 7: Create with duplicate email - should fail
echo -e "${YELLOW}Test 7: Duplicate email rejection${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Duplicate",
    "email": "'$NEW_EMAIL'",
    "password": "SecurePass123!",
    "role": "MODERATOR"
  }')
STATUS=$(echo "$RESP" | tail -1)
ERROR=$(echo "$RESP" | head -n -1 | jq -r '.error')
echo "Status: $STATUS | Error: $ERROR"
[ "$STATUS" = "409" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 8: Invalid password - should fail
echo -e "${YELLOW}Test 8: Invalid password rejection${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Bad Password",
    "email": "badpass@example.com",
    "password": "NoSpecialChar123",
    "role": "MODERATOR"
  }')
STATUS=$(echo "$RESP" | tail -1)
echo "Status: $STATUS (should be 422 for validation error)"
[ "$STATUS" = "422" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 9: Update staff name
echo -e "${YELLOW}Test 9: Update staff name${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/staff/$NEW_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name"
  }')
STATUS=$(echo "$RESP" | tail -1)
NAME=$(echo "$RESP" | head -n -1 | jq -r '.data.name')
echo "Status: $STATUS | New name: $NAME"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 10: Update staff role
echo -e "${YELLOW}Test 10: Update staff role to ADMIN${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/staff/$NEW_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "ADMIN"
  }')
STATUS=$(echo "$RESP" | tail -1)
ROLE=$(echo "$RESP" | head -n -1 | jq -r '.data.role')
echo "Status: $STATUS | New role: $ROLE"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 11: Deactivate staff
echo -e "${YELLOW}Test 11: Deactivate staff (set INACTIVE)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/staff/$NEW_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "INACTIVE"
  }')
STATUS=$(echo "$RESP" | tail -1)
STATUS_VAL=$(echo "$RESP" | head -n -1 | jq -r '.data.status')
echo "Status: $STATUS | Staff status: $STATUS_VAL"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 12: Update activity
echo -e "${YELLOW}Test 12: Update staff activity (last login)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/staff/$NEW_ID/activity" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
ACTIVITY=$(echo "$RESP" | head -n -1 | jq -r '.data.lastActivity')
echo "Status: $STATUS | Last activity: $ACTIVITY"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 13: Create staff for deletion
echo -e "${YELLOW}Test 13: Create staff for deletion test${NC}"
DEL_EMAIL="tobedeeted_$(date +%s)@example.com"
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "To Be Deleted",
    "email": "'$DEL_EMAIL'",
    "password": "SecurePass123!",
    "role": "MODERATOR"
  }')
DEL_ID=$(echo "$RESP" | head -n -1 | jq -r '.data.id')
echo "Created staff with ID: ${DEL_ID:0:20}..."
[ "$(echo "$RESP" | tail -1)" = "201" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 14: Delete staff
echo -e "${YELLOW}Test 14: Delete staff${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL/staff/$DEL_ID" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
echo "Status: $STATUS"
[ "$STATUS" = "200" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 15: Get deleted staff - should fail
echo -e "${YELLOW}Test 15: Get deleted staff (should fail)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/staff/$DEL_ID" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
ERROR=$(echo "$RESP" | head -n -1 | jq -r '.code')
echo "Status: $STATUS | Error: $ERROR"
[ "$STATUS" = "404" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 16: Cannot delete own account
echo -e "${YELLOW}Test 16: Cannot delete own account (should fail)${NC}"
ALPHA_ID=$(curl -s -X GET "$BASE_URL/staff?role=ADMIN" \
  -H "Authorization: Bearer $TOKEN" | jq -r '.data[0].id')
RESP=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL/staff/$ALPHA_ID" \
  -H "Authorization: Bearer $TOKEN")
STATUS=$(echo "$RESP" | tail -1)
ERROR=$(echo "$RESP" | head -n -1 | jq -r '.code')
echo "Status: $STATUS | Error: $ERROR"
[ "$STATUS" = "409" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 17: Cannot deactivate own account
echo -e "${YELLOW}Test 17: Cannot deactivate own account (should fail)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/staff/$ALPHA_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "INACTIVE"
  }')
STATUS=$(echo "$RESP" | tail -1)
ERROR=$(echo "$RESP" | head -n -1 | jq -r '.code')
echo "Status: $STATUS | Error: $ERROR"
[ "$STATUS" = "409" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Test 18: Cannot change own role
echo -e "${YELLOW}Test 18: Cannot change own role (should fail)${NC}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/staff/$ALPHA_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "MODERATOR"
  }')
STATUS=$(echo "$RESP" | tail -1)
ERROR=$(echo "$RESP" | head -n -1 | jq -r '.code')
echo "Status: $STATUS | Error: $ERROR"
[ "$STATUS" = "409" ] && echo "✓ PASS" || echo "✗ FAIL"
echo ""

# Final: Get all staff
echo -e "${YELLOW}Final: Get all staff in organization${NC}"
RESP=$(curl -s -X GET "$BASE_URL/staff" \
  -H "Authorization: Bearer $TOKEN")
TOTAL=$(echo "$RESP" | jq '.meta.pagination.total')
echo "Total staff: $TOTAL"
echo ""

echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "${GREEN}  Test Suite Complete!${NC}"
echo -e "${GREEN}════════════════════════════════════════${NC}"
