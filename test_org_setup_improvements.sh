#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

BASE_URL="http://localhost:8000/api/v1"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Organization Setup Improvements Test${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Test 1: Register user without org
echo -e "${YELLOW}Test 1: Register new user (without organizationCode)${NC}"
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test-org-'$(date +%s)'@test.com",
    "name": "Test User",
    "password": "Password123!",
    "password_confirmation": "Password123!"
  }')

echo $REGISTER_RESPONSE | jq '.'
EMAIL=$(echo $REGISTER_RESPONSE | jq -r '.data.email')
echo ""

# Test 2: Login to get token
echo -e "${YELLOW}Test 2: Login and get initial token${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "'$EMAIL'",
    "password": "Password123!"
  }')

echo $LOGIN_RESPONSE | jq '.'
TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.accessToken')
OLD_ORG_CODE=$(echo $LOGIN_RESPONSE | jq -r '.data | select(.organizationCode != null) | .organizationCode // "null"')

echo -e "${BLUE}Initial token organizationCode: $OLD_ORG_CODE${NC}"
echo ""

# Test 3: Check setup status before creating org
echo -e "${YELLOW}Test 3: Check setup status (should need setup)${NC}"
SETUP_CHECK_BEFORE=$(curl -s -X GET "$BASE_URL/organization/check-setup" \
  -H "Authorization: Bearer $TOKEN")

echo $SETUP_CHECK_BEFORE | jq '.'
echo ""

# Test 4: Create organization and check token
echo -e "${YELLOW}Test 4: Create organization (should return new token with organizationCode)${NC}"
CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/organization/create" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "organizationName": "Test Org Setup '$(date +%s)'",
    "description": "Testing organization setup token refresh"
  }')

echo $CREATE_RESPONSE | jq '.'
NEW_TOKEN=$(echo $CREATE_RESPONSE | jq -r '.data.accessToken')
NEW_ORG_CODE=$(echo $CREATE_RESPONSE | jq -r '.data.organizationCode')

# Decode JWT to see payload
echo -e "${BLUE}New token organizationCode from response: $NEW_ORG_CODE${NC}"
echo ""

# Test 5: Check setup status after creating org
echo -e "${YELLOW}Test 5: Check setup status (should not need setup anymore)${NC}"
SETUP_CHECK_AFTER=$(curl -s -X GET "$BASE_URL/organization/check-setup" \
  -H "Authorization: Bearer $NEW_TOKEN")

echo $SETUP_CHECK_AFTER | jq '.'
echo ""

# Test 6: Test error case - user tries to create second org
echo -e "${YELLOW}Test 6: Try to create second org (should fail with clear error message)${NC}"
CREATE_AGAIN=$(curl -s -X POST "$BASE_URL/organization/create" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "organizationName": "Second Org '$(date +%s)'",
    "description": "This should fail"
  }')

echo $CREATE_AGAIN | jq '.'
echo ""

# Test 7: Register another user and test join
echo -e "${YELLOW}Test 7: Register second user${NC}"
REGISTER_RESPONSE_2=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test-org-join-'$(date +%s)'@test.com",
    "name": "Join Test User",
    "password": "Password123!",
    "password_confirmation": "Password123!"
  }')

echo $REGISTER_RESPONSE_2 | jq '.'
EMAIL_2=$(echo $REGISTER_RESPONSE_2 | jq -r '.data.email')
echo ""

# Test 8: Login second user
echo -e "${YELLOW}Test 8: Login second user${NC}"
LOGIN_RESPONSE_2=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "'$EMAIL_2'",
    "password": "Password123!"
  }')

echo $LOGIN_RESPONSE_2 | jq '.'
TOKEN_2=$(echo $LOGIN_RESPONSE_2 | jq -r '.data.accessToken')
echo ""

# Test 9: Join organization and check token refresh
echo -e "${YELLOW}Test 9: Join organization (should return new token with organizationCode)${NC}"
JOIN_RESPONSE=$(curl -s -X POST "$BASE_URL/organization/join" \
  -H "Authorization: Bearer $TOKEN_2" \
  -H "Content-Type: application/json" \
  -d '{
    "organizationCode": "'$NEW_ORG_CODE'"
  }')

echo $JOIN_RESPONSE | jq '.'
NEW_TOKEN_2=$(echo $JOIN_RESPONSE | jq -r '.data.accessToken')
JOIN_ORG_CODE=$(echo $JOIN_RESPONSE | jq -r '.data.organizationCode')

echo -e "${BLUE}Join response token organizationCode: $JOIN_ORG_CODE${NC}"
echo ""

# Test 10: Try to join again (should fail)
echo -e "${YELLOW}Test 10: Try to join again (should fail with clear error)${NC}"
JOIN_AGAIN=$(curl -s -X POST "$BASE_URL/organization/join" \
  -H "Authorization: Bearer $NEW_TOKEN_2" \
  -H "Content-Type: application/json" \
  -d '{
    "organizationCode": "'$NEW_ORG_CODE'"
  }')

echo $JOIN_AGAIN | jq '.'
echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Test Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
