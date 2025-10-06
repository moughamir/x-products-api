#!/bin/bash
# Product Data Analysis Script
# Analyzes product JSON files and exports selected fields to CSV
#
# Usage:
#   ./bin/analyze-data.sh                                    # Default fields
#   ./bin/analyze-data.sh --fields id,title,price            # Custom fields
#   ./bin/analyze-data.sh --output custom.csv                # Custom output file
#   ./bin/analyze-data.sh --help                             # Show help

set -e

# Default configuration
OUTPUT_FILE="products_analysis.csv"
DATA_DIR="data/json/products_by_id"
FIELDS="id,title,handle,product_type,vendor,price,tags,has_variants,has_images"

# Parse command-line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --fields)
            FIELDS="$2"
            shift 2
            ;;
        --output|-o)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --data-dir)
            DATA_DIR="$2"
            shift 2
            ;;
        --help|-h)
            echo "Product Data Analysis Script"
            echo ""
            echo "Usage:"
            echo "  $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --fields FIELDS      Comma-separated list of fields to extract"
            echo "  --output FILE        Output CSV file (default: products_analysis.csv)"
            echo "  --data-dir DIR       Directory containing JSON files (default: data/json/products_by_id)"
            echo "  --help, -h           Show this help message"
            echo ""
            echo "Available fields:"
            echo "  id, title, handle, body_html, vendor, product_type, created_at, updated_at"
            echo "  price, compare_at_price, tags, has_variants, has_images, variant_count, image_count"
            echo ""
            echo "Examples:"
            echo "  $0 --fields id,title,price"
            echo "  $0 --fields id,title,vendor,product_type --output vendors.csv"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Validate data directory
if [ ! -d "$DATA_DIR" ]; then
    echo "Error: Data directory not found: $DATA_DIR"
    exit 1
fi

# Build jq filter based on requested fields
IFS=',' read -ra FIELD_ARRAY <<< "$FIELDS"
JQ_FIELDS=""
CSV_HEADER=""

for field in "${FIELD_ARRAY[@]}"; do
    field=$(echo "$field" | xargs)  # Trim whitespace

    if [ -n "$CSV_HEADER" ]; then
        CSV_HEADER="${CSV_HEADER},${field}"
    else
        CSV_HEADER="${field}"
    fi

    case $field in
        id|title|handle|body_html|vendor|product_type|created_at|updated_at)
            JQ_FIELDS="${JQ_FIELDS}, .${field}"
            ;;
        price)
            JQ_FIELDS="${JQ_FIELDS}, (if .variants != null and (.variants | length) > 0 then (.variants | map(.price | tonumber) | min) else null end)"
            ;;
        compare_at_price)
            JQ_FIELDS="${JQ_FIELDS}, (if .variants != null and (.variants | length) > 0 then (.variants | map(.compare_at_price | tonumber) | min) else null end)"
            ;;
        tags)
            JQ_FIELDS="${JQ_FIELDS}, (.tags | tostring)"
            ;;
        has_variants)
            JQ_FIELDS="${JQ_FIELDS}, (if .variants != null and (.variants | length) > 0 then \"true\" else \"false\" end)"
            ;;
        has_images)
            JQ_FIELDS="${JQ_FIELDS}, (if .images != null and (.images | length) > 0 then \"true\" else \"false\" end)"
            ;;
        variant_count)
            JQ_FIELDS="${JQ_FIELDS}, (if .variants != null then (.variants | length) else 0 end)"
            ;;
        image_count)
            JQ_FIELDS="${JQ_FIELDS}, (if .images != null then (.images | length) else 0 end)"
            ;;
        *)
            echo "Warning: Unknown field '$field', will attempt to extract as-is"
            JQ_FIELDS="${JQ_FIELDS}, .${field}"
            ;;
    esac
done

# Remove leading comma and space
JQ_FIELDS="${JQ_FIELDS:2}"

echo "========================================="
echo "Product Data Analysis"
echo "========================================="
echo "Data directory: $DATA_DIR"
echo "Output file: $OUTPUT_FILE"
echo "Fields: $FIELDS"
echo "========================================="

# Count total files
TOTAL_FILES=$(find "$DATA_DIR" -name "*.json" -type f | wc -l)
echo "Found $TOTAL_FILES product files"
echo ""

# Create CSV header
echo "$CSV_HEADER" > "$OUTPUT_FILE"

# Process all JSON files and append to CSV
PROCESSED=0
find "$DATA_DIR" -name "*.json" -type f | while read file; do
    jq -r "[${JQ_FIELDS}] | @csv" "$file" 2>/dev/null || echo "Error processing: $file" >&2
    PROCESSED=$((PROCESSED + 1))
    if [ $((PROCESSED % 100)) -eq 0 ]; then
        echo "Processed $PROCESSED / $TOTAL_FILES files..." >&2
    fi
done >> "$OUTPUT_FILE"

echo ""
echo "========================================="
echo "✓ Analysis Complete!"
echo "========================================="
echo "Output saved to: $OUTPUT_FILE"
echo "Total records: $(wc -l < "$OUTPUT_FILE" | xargs)"
echo "========================================="
