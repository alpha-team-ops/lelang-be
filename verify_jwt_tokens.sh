#!/bin/bash

# Decode JWT token (base64 decode the payload)
decode_jwt() {
    local token=$1
    local payload=$(echo $token | cut -d. -f2)
    # Add padding if needed
    padding=$(( (4 - ${#payload} % 4) % 4 ))
    payload="${payload}$(printf '%0.s=' $(seq 1 $padding))"
    echo $payload | base64 -d | jq '.' 2>/dev/null || echo "Failed to decode"
}

BASE_URL="http://localhost:8000/api/v1"

echo "=========================================="
echo "JWT Token Payload Verification"
echo "=========================================="
echo ""

# Step 1: Register user
echo "Step 1: Register user..."
REGISTER=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jwt-test-'$(date +%s)'@test.com",
    "name": "JWT Test User",
    "password": "Password123!",
    "password_confirmation": "Password123!"
  }')

EMAIL=$(echo $REGISTER | jq -r '.data.email')
echo "Registered: $EMAIL"
echo ""

# Step 2: Login
echo "Step 2: Login (before org setup)..."
LOGIN=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "'$EMAIL'",
    "password": "Password123!"
  }')

TOKEN_BEFORE=$(echo $LOGIN | jq -r '.data.accessToken')
echo "Payload BEFORE org setup:"
decode_jwt $TOKEN_BEFORE
echo ""

# Step 3: Create org
echo "Step 3: Create organization..."
CREATE=$(curl -s -X POST "$BASE_URL/organization/create" \
  -H "Authorization: Bearer $TOKEN_BEFORE" \
  -H "Content-Type: application/json" \
  -d '{
    "organizationName": "JWT Test Org '$(date +%s)'",
    "description": "Testing JWT payload update"
  }')

TOKEN_AFTER=$(echo $CREATE | jq -r '.data.accessToken')
ORG_CODE=$(echo $CREATE | jq -r '.data.organizationCode')

echo "Organization Code: $ORG_CODE"
echo ""
echo "Payload AFTER org setup (from create response):"
decode_jwt $TOKEN_AFTER
echo ""

# Verify the difference
echo "=========================================="
echo "Verification Results:"
echo "=========================================="
BEFORE_ORG=$(decode_jwt $TOKEN_BEFORE | jq -r '.organizationCode')
AFTER_ORG=$(decode_jwt $TOKEN_AFTER | jq -r '.organizationCode')
BEFORE_ROLE=$(decode_jwt $TOKEN_BEFORE | jq -r '.role')
AFTER_ROLE=$(decode_jwt $TOKEN_AFTER | jq -r '.role')

echo "organizationCode BEFORE: $BEFORE_ORG"
echo "organizationCode AFTER: $AFTER_ORG"
echo "Expected: $ORG_CODE"
echo "✓ Match: $([ "$AFTER_ORG" = "$ORG_CODE" ] && echo 'YES' || echo 'NO')"
echo ""
echo "role BEFORE: $BEFORE_ROLE"
echo "role AFTER: $AFTER_ROLE"
echo "✓ Role updated to ADMIN: $([ "$AFTER_ROLE" = "ADMIN" ] && echo 'YES' || echo 'NO')"
echo ""
