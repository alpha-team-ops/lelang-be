#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

API_URL="http://localhost:8000/api/v1"
ADMIN_EMAIL="alpha.dev@deraly.id"
ADMIN_PASSWORD="SecurePassword123!"
MODERATOR_EMAIL="moderator@deraly.id"

echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}         Authentication API Testing Script${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}\n"

# Test 1: Login Admin
echo -e "${YELLOW}[TEST 1] Login Admin User${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$ADMIN_EMAIL\",
    \"password\": \"$ADMIN_PASSWORD\"
  }")

echo "$LOGIN_RESPONSE" | jq .
ACCESS_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.accessToken // empty')
REFRESH_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.refreshToken // empty')

if [ -n "$ACCESS_TOKEN" ]; then
  echo -e "${GREEN}✓ Login successful${NC}\n"
else
  echo -e "${RED}✗ Login failed${NC}\n"
  exit 1
fi

# Test 2: Verify Token
echo -e "${YELLOW}[TEST 2] Verify Access Token${NC}"
VERIFY_RESPONSE=$(curl -s -X GET "$API_URL/auth/verify" \
  -H "Authorization: Bearer $ACCESS_TOKEN")

echo "$VERIFY_RESPONSE" | jq .
if echo "$VERIFY_RESPONSE" | jq -e '.data.valid == true' > /dev/null; then
  echo -e "${GREEN}✓ Token verification successful${NC}\n"
else
  echo -e "${RED}✗ Token verification failed${NC}\n"
fi

# Test 3: Register New User
echo -e "${YELLOW}[TEST 3] Register New User${NC}"
NEW_USER_EMAIL="newuser_$(date +%s)@deraly.id"
REGISTER_RESPONSE=$(curl -s -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"New Test User\",
    \"email\": \"$NEW_USER_EMAIL\",
    \"password\": \"TestPassword123!\",
    \"organizationCode\": \"ORG-DERALY-001\"
  }")

echo "$REGISTER_RESPONSE" | jq .
if echo "$REGISTER_RESPONSE" | jq -e '.success == true' > /dev/null; then
  echo -e "${GREEN}✓ Registration successful${NC}\n"
else
  echo -e "${RED}✗ Registration failed${NC}\n"
fi

# Test 4: Login with Invalid Password
echo -e "${YELLOW}[TEST 4] Login with Invalid Password (Should Fail)${NC}"
INVALID_LOGIN=$(curl -s -X POST "$API_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$ADMIN_EMAIL\",
    \"password\": \"WrongPassword123!\"
  }")

echo "$INVALID_LOGIN" | jq .
if echo "$INVALID_LOGIN" | jq -e '.success == false' > /dev/null; then
  echo -e "${GREEN}✓ Invalid login correctly rejected${NC}\n"
else
  echo -e "${RED}✗ Invalid login was accepted (should fail)${NC}\n"
fi

# Test 5: Verify Invalid Token (Should Fail)
echo -e "${YELLOW}[TEST 5] Verify Invalid Token (Should Fail)${NC}"
INVALID_TOKEN_VERIFY=$(curl -s -X GET "$API_URL/auth/verify" \
  -H "Authorization: Bearer invalid_token_here")

echo "$INVALID_TOKEN_VERIFY" | jq .
if echo "$INVALID_TOKEN_VERIFY" | jq -e '.success == false' > /dev/null; then
  echo -e "${GREEN}✓ Invalid token correctly rejected${NC}\n"
else
  echo -e "${RED}✗ Invalid token was accepted (should fail)${NC}\n"
fi

# Test 6: Change Password
echo -e "${YELLOW}[TEST 6] Change Password${NC}"
CHANGE_PASSWORD=$(curl -s -X POST "$API_URL/auth/change-password" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"currentPassword\": \"$ADMIN_PASSWORD\",
    \"newPassword\": \"NewPassword123!\"
  }")

echo "$CHANGE_PASSWORD" | jq .
if echo "$CHANGE_PASSWORD" | jq -e '.success == true' > /dev/null; then
  echo -e "${GREEN}✓ Password changed successfully${NC}\n"
else
  echo -e "${RED}✗ Password change failed${NC}\n"
fi

# Test 7: Refresh Token
echo -e "${YELLOW}[TEST 7] Refresh Access Token${NC}"
REFRESH_RESPONSE=$(curl -s -X POST "$API_URL/auth/refresh" \
  -H "Content-Type: application/json" \
  -d "{
    \"refreshToken\": \"$REFRESH_TOKEN\"
  }")

echo "$REFRESH_RESPONSE" | jq .
if echo "$REFRESH_RESPONSE" | jq -e '.data.accessToken' > /dev/null; then
  echo -e "${GREEN}✓ Token refresh successful${NC}\n"
  NEW_ACCESS_TOKEN=$(echo "$REFRESH_RESPONSE" | jq -r '.data.accessToken')
else
  echo -e "${RED}✗ Token refresh failed${NC}\n"
fi

# Test 8: Logout
echo -e "${YELLOW}[TEST 8] Logout${NC}"
LOGOUT_RESPONSE=$(curl -s -X POST "$API_URL/auth/logout" \
  -H "Authorization: Bearer $NEW_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"refreshToken\": \"$REFRESH_TOKEN\"
  }")

echo "$LOGOUT_RESPONSE" | jq .
if echo "$LOGOUT_RESPONSE" | jq -e '.success == true' > /dev/null; then
  echo -e "${GREEN}✓ Logout successful${NC}\n"
else
  echo -e "${RED}✗ Logout failed${NC}\n"
fi

# Test 9: Request Password Reset
echo -e "${YELLOW}[TEST 9] Request Password Reset${NC}"
FORGOT_PASSWORD=$(curl -s -X POST "$API_URL/auth/forgot-password" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$ADMIN_EMAIL\"
  }")

echo "$FORGOT_PASSWORD" | jq .
if echo "$FORGOT_PASSWORD" | jq -e '.success == true' > /dev/null; then
  echo -e "${GREEN}✓ Password reset requested${NC}\n"
else
  echo -e "${RED}✗ Password reset request failed${NC}\n"
fi

# Test 10: Register with Duplicate Email
echo -e "${YELLOW}[TEST 10] Register with Duplicate Email (Should Fail)${NC}"
DUPLICATE_REGISTER=$(curl -s -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Duplicate User\",
    \"email\": \"$ADMIN_EMAIL\",
    \"password\": \"TestPassword123!\",
    \"organizationCode\": \"ORG-DERALY-001\"
  }")

echo "$DUPLICATE_REGISTER" | jq .
if echo "$DUPLICATE_REGISTER" | jq -e '.success == false' > /dev/null; then
  echo -e "${GREEN}✓ Duplicate email correctly rejected${NC}\n"
else
  echo -e "${RED}✗ Duplicate email was accepted (should fail)${NC}\n"
fi

echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Testing completed!${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}\n"
