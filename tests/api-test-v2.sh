#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

API_URL="http://localhost:8000/api/v1"
TESTS_PASSED=0
TESTS_FAILED=0

# Test function
test_endpoint() {
    local test_num=$1
    local method=$2
    local endpoint=$3
    local data=$4
    local headers=$5
    local expected_success=$6
    local description=$7

    echo -e "${BLUE}TEST $test_num: $description${NC}"
    echo "  $method $endpoint"

    if [ -z "$headers" ]; then
        response=$(curl -s -X $method "$API_URL$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data")
    else
        response=$(curl -s -X $method "$API_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "$headers" \
            -d "$data")
    fi

    success=$(echo $response | jq -r '.success // empty')
    
    if [ "$success" = "$expected_success" ]; then
        echo -e "  ${GREEN}✓ PASSED${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "  ${RED}✗ FAILED${NC}"
        echo "  Response: $(echo $response | jq '.')"
        ((TESTS_FAILED++))
    fi
    echo ""
}

echo -e "${YELLOW}========== Authentication API v1 Tests ==========${NC}\n"

# TEST 1: Login with valid credentials
test_endpoint 1 "POST" "/auth/login" \
    '{"email":"alpha.dev@deraly.id","password":"NewPassword456!"}' \
    "" \
    "true" \
    "Login with valid credentials"

# Get tokens for protected endpoints
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"alpha.dev@deraly.id","password":"NewPassword456!"}')

ACCESS_TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.accessToken')
REFRESH_TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.refreshToken')

echo -e "${YELLOW}Got tokens:${NC}"
echo "  ACCESS_TOKEN: ${ACCESS_TOKEN:0:50}..."
echo "  REFRESH_TOKEN: ${REFRESH_TOKEN:0:50}..."
echo ""

# TEST 2: Register new user
TEST_EMAIL="test_$(date +%s)@deraly.id"
test_endpoint 2 "POST" "/auth/register" \
    "{\"name\":\"Test User\",\"email\":\"$TEST_EMAIL\",\"password\":\"SecurePassword123!\",\"organizationCode\":\"ORG-DERALY-001\",\"role\":\"MODERATOR\"}" \
    "" \
    "true" \
    "Register new user"

# TEST 3: Login with invalid password
test_endpoint 3 "POST" "/auth/login" \
    '{"email":"alpha.dev@deraly.id","password":"WrongPassword"}' \
    "" \
    "false" \
    "Login with invalid password (should fail)"

# TEST 4: Verify token (protected)
test_endpoint 4 "GET" "/auth/verify" \
    "" \
    "Authorization: Bearer $ACCESS_TOKEN" \
    "true" \
    "Verify valid token"

# TEST 5: Verify with invalid token (protected)
test_endpoint 5 "GET" "/auth/verify" \
    "" \
    "Authorization: Bearer invalid_token_123" \
    "false" \
    "Verify invalid token"

# TEST 6: Change password (protected)
test_endpoint 6 "POST" "/auth/change-password" \
    '{"currentPassword":"NewPassword456!","newPassword":"AnotherPassword789!"}' \
    "Authorization: Bearer $ACCESS_TOKEN" \
    "true" \
    "Change password"

# TEST 7: Refresh token
test_endpoint 7 "POST" "/auth/refresh" \
    "{\"refreshToken\":\"$REFRESH_TOKEN\"}" \
    "" \
    "true" \
    "Refresh access token"

# TEST 8: Forgot password request
test_endpoint 8 "POST" "/auth/forgot-password" \
    '{"email":"alpha.dev@deraly.id"}' \
    "" \
    "true" \
    "Request password reset"

# TEST 9: Register with duplicate email
test_endpoint 9 "POST" "/auth/register" \
    '{"name":"Duplicate User","email":"alpha.dev@deraly.id","password":"SecurePassword123!","organizationCode":"ORG-DERALY-001","role":"MODERATOR"}' \
    "" \
    "false" \
    "Register with duplicate email (should fail)"

# TEST 10: Logout (protected)
test_endpoint 10 "POST" "/auth/logout" \
    "{\"refreshToken\":\"$REFRESH_TOKEN\"}" \
    "Authorization: Bearer $ACCESS_TOKEN" \
    "true" \
    "Logout and revoke refresh token"

# TEST 11: Verify token after logout (should still work - needs to be checked in refresh token blacklist)
test_endpoint 11 "GET" "/auth/verify" \
    "" \
    "Authorization: Bearer $ACCESS_TOKEN" \
    "true" \
    "Verify token after logout (access token still valid)"

# TEST 12: Try to use revoked refresh token
test_endpoint 12 "POST" "/auth/refresh" \
    "{\"refreshToken\":\"$REFRESH_TOKEN\"}" \
    "" \
    "false" \
    "Try refresh with revoked token (should fail)"

# TEST 13: Register with weak password
test_endpoint 13 "POST" "/auth/register" \
    "{\"name\":\"Weak Pass User\",\"email\":\"weakpass_$(date +%s)@deraly.id\",\"password\":\"weak\",\"organizationCode\":\"ORG-DERALY-001\",\"role\":\"MODERATOR\"}" \
    "" \
    "false" \
    "Register with weak password (should fail)"

# TEST 14: Register with non-existent organization
test_endpoint 14 "POST" "/auth/register" \
    "{\"name\":\"Bad Org User\",\"email\":\"badorg_$(date +%s)@deraly.id\",\"password\":\"SecurePassword123!\",\"organizationCode\":\"ORG-NONEXIST\",\"role\":\"MODERATOR\"}" \
    "" \
    "false" \
    "Register with non-existent organization (should fail)"

# TEST 15: Moderator login and verify role
MODERATOR_LOGIN=$(curl -s -X POST "$API_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"moderator@deraly.id","password":"SecurePassword123!"}')

MODERATOR_TOKEN=$(echo $MODERATOR_LOGIN | jq -r '.data.accessToken')
MODERATOR_ROLE=$(echo $MODERATOR_LOGIN | jq -r '.data.data.role')

if [ "$MODERATOR_ROLE" = "MODERATOR" ]; then
    echo -e "${BLUE}TEST 15: Login as moderator and verify role${NC}"
    echo "  ${GREEN}✓ PASSED${NC}"
    ((TESTS_PASSED++))
else
    echo -e "${BLUE}TEST 15: Login as moderator and verify role${NC}"
    echo "  ${RED}✗ FAILED${NC}"
    ((TESTS_FAILED++))
fi
echo ""

# Summary
echo -e "${YELLOW}========== Test Summary ==========${NC}"
echo -e "Total Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Total Failed: ${RED}$TESTS_FAILED${NC}"
echo -e "Total Tests: $((TESTS_PASSED + TESTS_FAILED))"

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "\n${GREEN}All tests passed! ✓${NC}"
    exit 0
else
    echo -e "\n${RED}Some tests failed!${NC}"
    exit 1
fi
