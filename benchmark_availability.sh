#!/bin/bash
#
# Benchmark: Monthly Availability Calculation Time
# Tests how long it takes to calculate timeslots for an entire month
#
# Usage: ./benchmark_availability.sh [service_id] [year] [month]
# Example: ./benchmark_availability.sh 3 2025 12
#

SERVICE_ID=${1:-3}
YEAR=${2:-2025}
MONTH=${3:-12}

# Calculate start and end dates
START_DATE="${YEAR}-$(printf '%02d' $MONTH)-01"
END_DATE=$(date -j -v+1m -v1d -v-1d -f "%Y-%m-%d" "${START_DATE}" "+%Y-%m-%d" 2>/dev/null || date -d "${START_DATE} +1 month -1 day" "+%Y-%m-%d")

# API Base URL - UPDATE THIS TO YOUR SITE
API_BASE="https://www.oceanshiatsu.at/wp-json/osb/v1"

echo "========================================"
echo "  Availability Benchmark"
echo "========================================"
echo "Service ID: $SERVICE_ID"
echo "Date Range: $START_DATE to $END_DATE"
echo ""

# Test 1: Monthly availability (from index table - should be fast)
echo "--- Test 1: /availability/month (indexed) ---"
time curl -s -o /tmp/month_response.json \
  "${API_BASE}/availability/month?month=${YEAR}-$(printf '%02d' $MONTH)&service_id=${SERVICE_ID}"
echo ""
echo "Response size: $(wc -c < /tmp/month_response.json) bytes"
echo "Response preview:"
head -c 200 /tmp/month_response.json
echo ""
echo ""

# Test 2: Full month slot calculation via date range
echo "--- Test 2: /availability (full calculation, date range) ---"
time curl -s -o /tmp/range_response.json \
  "${API_BASE}/availability?start_date=${START_DATE}&end_date=${END_DATE}&service_id=${SERVICE_ID}"
echo ""
echo "Response size: $(wc -c < /tmp/range_response.json) bytes"
echo "Response preview:"
head -c 500 /tmp/range_response.json
echo ""
echo ""

# Test 3: Single day calculation (for comparison)
echo "--- Test 3: /availability (single day) ---"
SINGLE_DATE="${YEAR}-$(printf '%02d' $MONTH)-15"
time curl -s -o /tmp/single_response.json \
  "${API_BASE}/availability?date=${SINGLE_DATE}&service_id=${SERVICE_ID}"
echo ""
echo "Response size: $(wc -c < /tmp/single_response.json) bytes"
echo "Response:"
cat /tmp/single_response.json
echo ""

echo ""
echo "========================================"
echo "  Summary"
echo "========================================"
echo "Test 1 (monthly index): Fast lookup, no slot times"
echo "Test 2 (full month calc): Slower, includes all slot times"
echo "Test 3 (single day): Baseline for individual day fetch"
echo ""
echo "Decision: If Test 2 < 2 seconds, pre-fetching full month is viable."
