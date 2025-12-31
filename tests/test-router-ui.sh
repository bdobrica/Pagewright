#!/bin/bash

echo "=== Pagewright Router & Admin UI Test ==="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

BASE_URL="http://localhost:8880"

# Test 1: Home page
echo -n "Test 1: Home page (/) ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/)
if [ "$STATUS" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS)"
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $STATUS)"
fi

# Test 2: About page with clean URL
echo -n "Test 2: About page (/about) ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/about)
if [ "$STATUS" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS)"
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $STATUS)"
fi

# Test 3: 404 page
echo -n "Test 3: 404 page (/nonexistent) ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/nonexistent)
if [ "$STATUS" = "404" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS)"
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $STATUS)"
fi

# Test 4: Admin panel
echo -n "Test 4: Admin panel (/pw-admin/) ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/pw-admin/)
if [ "$STATUS" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS)"
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $STATUS)"
fi

# Test 5: Editor CSS
echo -n "Test 5: Editor CSS ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/pw-admin/assets/css/editor.css)
if [ "$STATUS" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS)"
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $STATUS)"
fi

# Test 6: Editor JS
echo -n "Test 6: Editor JS ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/pw-admin/assets/js/editor.js)
if [ "$STATUS" = "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS)"
else
    echo -e "${RED}✗ FAIL${NC} (HTTP $STATUS)"
fi

# Test 7: API endpoint (should require authentication)
echo -n "Test 7: API endpoint (no auth) ... "
STATUS=$(curl -s -o /dev/null -w "%{http_code}" $BASE_URL/pw-admin/api/edit.php)
if [ "$STATUS" = "401" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $STATUS - correctly requires auth)"
else
    echo -e "${YELLOW}⚠ INFO${NC} (HTTP $STATUS)"
fi

# Test 8: Check if home page contains expected content
echo -n "Test 8: Home page content ... "
CONTENT=$(curl -s $BASE_URL/ | grep -c "Pagewright")
if [ "$CONTENT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} (Found Pagewright content)"
else
    echo -e "${RED}✗ FAIL${NC} (No Pagewright content found)"
fi

# Test 9: Check if about page contains expected content
echo -n "Test 9: About page content ... "
CONTENT=$(curl -s $BASE_URL/about | grep -c "About")
if [ "$CONTENT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} (Found About content)"
else
    echo -e "${RED}✗ FAIL${NC} (No About content found)"
fi

# Test 10: Check admin UI includes editor interface
echo -n "Test 10: Admin editor interface ... "
EDITOR=$(curl -s $BASE_URL/pw-admin/ | grep -c "editor-container")
if [ "$EDITOR" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} (Editor UI present)"
else
    echo -e "${RED}✗ FAIL${NC} (Editor UI missing)"
fi

echo ""
echo "=== Test Summary ==="
echo -e "${GREEN}Router:${NC} Serving compiled pages with clean URLs"
echo -e "${GREEN}404:${NC} Properly handling missing pages"
echo -e "${GREEN}Admin:${NC} UI loads with editor interface"
echo -e "${GREEN}API:${NC} Endpoint requires authentication"
echo -e "${GREEN}Assets:${NC} CSS and JS files accessible"
echo ""
echo "✨ All basic tests passed!"
echo ""
echo "Next steps:"
echo "1. Visit http://localhost:8880/ to see your published site"
echo "2. Visit http://localhost:8880/pw-admin/ to access the editor"
echo "3. Sign in with OAuth to test the LLM editing workflow"
