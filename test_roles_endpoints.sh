#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api/v1"

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0

# Login first to get token
echo -e "${BLUE}=== Logging in to get token ===${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "alpha.dev@localhost",
    "password": "Alpha123!"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.accessToken')
ADMIN_ID=$(echo $LOGIN_RESPONSE | jq -r '.data.user.id')

echo "Token: $TOKEN"
echo "Admin ID: $ADMIN_ID"

# Test function
test_endpoint() {
  local test_name=$1
  local method=$2
  local endpoint=$3
  local data=$4
  local expected_status=$5

  TOTAL_TESTS=$((TOTAL_TESTS + 1))
  
  echo -e "\n${YELLOW}Test $TOTAL_TESTS: $test_name${NC}"
  
  if [ "$method" = "GET" ]; then
    RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "$API_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN")
  elif [ "$method" = "POST" ]; then
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$API_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d "$data")
  elif [ "$method" = "PUT" ]; then
    RESPONSE=$(curl -s -w "\n%{http_code}" -X PUT "$API_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/json" \
      -d "$data")
  elif [ "$method" = "DELETE" ]; then
    RESPONSE=$(curl -s -w "\n%{http_code}" -X DELETE "$API_URL$endpoint" \
      -H "Authorization: Bearer $TOKEN")
  fi

  HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
  BODY=$(echo "$RESPONSE" | sed '$d')

  echo "Expected Status: $expected_status, Got: $HTTP_CODE"
  
  if [ "$HTTP_CODE" = "$expected_status" ]; then
    echo -e "${GREEN}✓ PASS${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
    echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
  else
    echo -e "${RED}✗ FAIL${NC}"
    echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
  fi
}

# Test 1: Get all roles
test_endpoint "Get all roles" "GET" "/roles" "" "200"

# Test 2: Get permissions
test_endpoint "Get all permissions" "GET" "/roles/permissions/all" "" "200"

# Store a role ID for further tests
ADMIN_ROLE_ID="6662088f-bca4-477d-923b-7dcf7ed43a97"

# Test 3: Get role by ID
test_endpoint "Get role by ID" "GET" "/roles/$ADMIN_ROLE_ID" "" "200"

# Test 4: Create new role
test_endpoint "Create new role" "POST" "/roles" '{
  "name": "Editor",
  "description": "Content editor role",
  "permissions": ["manage_auctions", "view_analytics"]
}' "201"

# Extract created role ID from the response for further tests
CREATE_RESPONSE=$(curl -s -X POST "$API_URL/roles" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Reviewer",
    "description": "Content reviewer role",
    "permissions": ["view_analytics", "view_auctions"]
  }')
REVIEWER_ROLE_ID=$(echo $CREATE_RESPONSE | jq -r '.data.id')

echo -e "\n${BLUE}Created Reviewer role ID: $REVIEWER_ROLE_ID${NC}"

# Test 5: Duplicate role name
test_endpoint "Duplicate role name (409)" "POST" "/roles" '{
  "name": "Admin",
  "description": "Duplicate test",
  "permissions": []
}' "409"

# Test 6: Update role
test_endpoint "Update role description" "PUT" "/roles/$REVIEWER_ROLE_ID" '{
  "description": "Updated reviewer role",
  "isActive": true
}' "200"

# Test 7: Assign role to staff
test_endpoint "Assign role to staff" "POST" "/roles/$REVIEWER_ROLE_ID/assign" '{
  "staffId": "'$ADMIN_ID'"
}' "200"

# Test 8: Duplicate role assignment
test_endpoint "Duplicate role assignment (409)" "POST" "/roles/$REVIEWER_ROLE_ID/assign" '{
  "staffId": "'$ADMIN_ID'"
}' "409"

# Test 9: Unassign role from staff
test_endpoint "Unassign role from staff" "DELETE" "/roles/$REVIEWER_ROLE_ID/unassign?staffId=$ADMIN_ID" "" "200"

# Test 10: Try to delete built-in role (should fail)
test_endpoint "Cannot delete built-in Admin role" "DELETE" "/roles/$ADMIN_ROLE_ID" "" "409"

# Test 11: Delete custom role
test_endpoint "Delete custom role" "DELETE" "/roles/$REVIEWER_ROLE_ID" "" "200"

# Test 12: Get deleted role (should return 404)
test_endpoint "Get deleted role (404)" "GET" "/roles/$REVIEWER_ROLE_ID" "" "404"

# Test 13: Get non-existent role
test_endpoint "Get non-existent role" "GET" "/roles/00000000-0000-0000-0000-000000000000" "" "404"

# Test 14: Filter roles by active status
test_endpoint "Filter roles by isActive=true" "GET" "/roles?isActive=true" "" "200"

# Test 15: Pagination
test_endpoint "Get roles with pagination" "GET" "/roles?page=1&limit=5" "" "200"

# Test 16: Role validation - invalid permissions
test_endpoint "Create role with invalid permission" "POST" "/roles" '{
  "name": "TestRole",
  "description": "Test",
  "permissions": ["invalid_permission_id"]
}' "422"

# Test 17: Role validation - missing name
test_endpoint "Create role without name" "POST" "/roles" '{
  "description": "Test",
  "permissions": []
}' "422"

# Test 18: Try to assign non-existent role
test_endpoint "Assign non-existent role to staff" "POST" "/roles/00000000-0000-0000-0000-000000000000/assign" '{
  "staffId": "'$ADMIN_ID'"
}' "404"

# Summary
echo -e "\n${BLUE}================================${NC}"
echo -e "${BLUE}Test Summary${NC}"
echo -e "${BLUE}================================${NC}"
echo -e "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$((TOTAL_TESTS - PASSED_TESTS))${NC}"

if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
  echo -e "\n${GREEN}All tests passed!${NC}"
  exit 0
else
  echo -e "\n${RED}Some tests failed!${NC}"
  exit 1
fi
