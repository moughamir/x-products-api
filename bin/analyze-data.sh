# First, create the CSV header
echo "id,tags,product_type,has_variants,has_images,body_html" > products_analysis.csv

# Then process all JSON files and append to CSV
find data/json/products_by_id -name "*.json" -type f | \
  while read file; do
    jq -r '[
      .id, 
      (.tags | tostring), 
      .product_type, 
      (if .variants != null and (.variants | length) > 0 then "true" else "false" end), 
      (if .images != null and (.images | length) > 0 then "true" else "false" end), 
      (.body_html | tostring)
    ] | @csv' "$file"
  done >> products_analysis.csv
