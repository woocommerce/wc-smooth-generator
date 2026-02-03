# Testing Guide for Exact Ratio Distribution

This document provides comprehensive test cases for verifying the exact ratio distribution feature for coupons and refunds in batch mode.

## Prerequisites

- WordPress installation with WooCommerce
- WC Smooth Generator plugin installed
- WP-CLI access
- Some products already generated (run `wp wc generate products 50` if needed)

## Test Cases

### 1. Basic Coupon Ratio Tests

#### Test 1.1: Exact 50% coupon ratio
```bash
wp wc generate orders 100 --coupon-ratio=0.5
```
**Expected Result:** Exactly 50 orders with coupons

**Verification:**
```bash
# Count orders with coupons via database
wp db query "SELECT COUNT(DISTINCT order_id) FROM wp_woocommerce_order_items WHERE order_item_type = 'coupon'"
```

#### Test 1.2: Edge case - 0.0 ratio (no coupons)
```bash
wp wc generate orders 50 --coupon-ratio=0.0
```
**Expected Result:** 0 orders with coupons

#### Test 1.3: Edge case - 1.0 ratio (all coupons)
```bash
wp wc generate orders 50 --coupon-ratio=1.0
```
**Expected Result:** Exactly 50 orders with coupons

#### Test 1.4: Odd number rounding
```bash
wp wc generate orders 11 --coupon-ratio=0.5
```
**Expected Result:** Exactly 6 orders with coupons (5.5 rounds to 6)

### 2. Basic Refund Ratio Tests

#### Test 2.1: Exact 40% refund ratio with distribution
```bash
wp wc generate orders 100 --status=completed --refund-ratio=0.4
```
**Expected Result:**
- Total: 40 refunds
- Distribution: ~20 full, ~10 single partial, ~10 multi-partial

**Verification:**
```bash
# Count total refunds
wp db query "SELECT COUNT(*) FROM wp_posts WHERE post_type = 'shop_order_refund'"

# Count full refunds (orders with status 'refunded')
wp db query "SELECT COUNT(*) FROM wp_posts WHERE post_type = 'shop_order' AND post_status = 'wc-refunded'"
```

#### Test 2.2: Edge case - 0.0 ratio (no refunds)
```bash
wp wc generate orders 50 --status=completed --refund-ratio=0.0
```
**Expected Result:** 0 refunds

#### Test 2.3: Edge case - 1.0 ratio (all refunds)
```bash
wp wc generate orders 50 --status=completed --refund-ratio=1.0
```
**Expected Result:** Exactly 50 refunds (distributed 50/25/25)

#### Test 2.4: Odd number rounding for refunds
```bash
wp wc generate orders 11 --status=completed --refund-ratio=0.4
```
**Expected Result:**
- Total: 4 refunds (rounded)
- Distribution: ~2 full, ~1 partial, ~1 multi (remainder)

### 3. Threshold Boundary Tests

#### Test 3.1: At threshold (10,000 orders) - Should use exact ratios
```bash
wp wc generate orders 10000 --coupon-ratio=0.5
```
**Expected Result:**
- No warning message
- Exactly 5,000 orders with coupons

#### Test 3.2: Above threshold (10,001 orders) - Should use probabilistic
```bash
wp wc generate orders 10001 --coupon-ratio=0.5
```
**Expected Result:**
- Warning: "Batch size (10001) exceeds threshold (10000). Using probabilistic distribution..."
- Approximately 5,000 orders with coupons (not exact, will vary by ~1-2%)

### 4. Parameter Precedence Tests

#### Test 4.1: Legacy --coupons flag
```bash
wp wc generate orders 20 --coupons
```
**Expected Result:** All 20 orders have coupons (legacy behavior preserved)

#### Test 4.2: Coupon ratio without legacy flag
```bash
wp wc generate orders 100 --coupon-ratio=0.3
```
**Expected Result:** Exactly 30 orders with coupons

#### Test 4.3: Both flags (ratio should be ignored when legacy flag present)
```bash
wp wc generate orders 100 --coupons --coupon-ratio=0.3
```
**Expected Result:** All 100 orders have coupons (--coupons takes precedence)

### 5. Single Order Generation (Probabilistic Fallback)

#### Test 5.1: Single order with coupon ratio should use probabilistic
```bash
# Run multiple times to verify probabilistic behavior
wp wc generate orders 1 --coupon-ratio=0.5
wp wc generate orders 1 --coupon-ratio=0.5
wp wc generate orders 1 --coupon-ratio=0.5
```
**Expected Result:** Approximately 50% of single orders will have coupons (varies each run)

### 6. Refund Distribution Verification

#### Test 6.1: Verify 50/25/25 refund split
```bash
# Generate orders and check distribution
wp wc generate orders 200 --status=completed --refund-ratio=0.5
```
**Expected Result:**
- Total refunds: 100
- Full refunds (~50): Orders with status "refunded"
- Single partial (~25): Orders with 1 refund, status still "completed"
- Multi-partial (~25): Orders with 2 refunds, status still "completed"

**Manual Verification:**
1. Check a sample of fully refunded orders in WP Admin
2. Check a sample of partially refunded orders
3. Count number of refund entries per order

### 7. Combined Parameters

#### Test 7.1: Date range + coupon ratio + refund ratio
```bash
wp wc generate orders 100 --date-start=2024-01-01 --date-end=2024-12-31 --status=completed --coupon-ratio=0.4 --refund-ratio=0.3
```
**Expected Result:**
- Orders spread across date range
- Exactly 40 orders with coupons
- Exactly 30 orders with refunds (distributed 50/25/25)

### 8. Failed Orders Edge Case

#### Test 8.1: Verify failed orders don't affect count
```bash
# If products are missing or invalid, some orders may fail
wp wc generate orders 100 --coupon-ratio=0.5
```
**Expected Result:**
- Count only successful orders
- If only 95 orders succeed, there should be exactly 47-48 with coupons (based on 95, not 100)

## Verification Queries

### Count Orders with Coupons
```bash
wp db query "SELECT COUNT(DISTINCT order_id) FROM wp_woocommerce_order_items WHERE order_item_type = 'coupon'"
```

### Count All Refunds
```bash
wp db query "SELECT COUNT(*) FROM wp_posts WHERE post_type = 'shop_order_refund'"
```

### Count Full Refunds (Orders with 'refunded' status)
```bash
wp db query "SELECT COUNT(*) FROM wp_posts WHERE post_type = 'shop_order' AND post_status = 'wc-refunded'"
```

### Count Partial Refunds
```bash
wp db query "
SELECT COUNT(*) as partial_refund_orders
FROM (
    SELECT p.ID, COUNT(r.ID) as refund_count
    FROM wp_posts p
    LEFT JOIN wp_posts r ON r.post_parent = p.ID AND r.post_type = 'shop_order_refund'
    WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed'
    GROUP BY p.ID
    HAVING refund_count > 0
) as refunded_completed
"
```

### Count Multi-Partial Refunds (2 refunds on same order)
```bash
wp db query "
SELECT COUNT(*) as multi_partial_orders
FROM (
    SELECT p.ID, COUNT(r.ID) as refund_count
    FROM wp_posts p
    LEFT JOIN wp_posts r ON r.post_parent = p.ID AND r.post_type = 'shop_order_refund'
    WHERE p.post_type = 'shop_order'
    GROUP BY p.ID
    HAVING refund_count = 2
) as multi_refunded
"
```

## Notes

- All exact ratio tests assume successful order generation
- For large batches (>10,000), expect probabilistic distribution with ~1-2% variance
- Ratios are rounded using PHP's `round()` function for odd numbers
- Memory limit may affect very large batch tests (>50,000 orders)
