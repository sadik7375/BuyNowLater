import re

# Read the UTF-8 converted sidebar index view
with open('scratch/index_sidebar_utf8.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Remove "Price Plan" and "How It Works" buttons from sidebar navigation
# Let's match the buttons exactly
btn_price_plan = re.compile(
    r'<button class="sidebar-btn"[^>]*onclick="switchTab\(event, \'tab-price-plan\'\)"[^>]*>[\s\S]*?</button>\s*'
)
content = btn_price_plan.sub('', content)

btn_how_it_works = re.compile(
    r'<button class="sidebar-btn"[^>]*onclick="switchTab\(event, \'tab-how-it-works\'\)"[^>]*>[\s\S]*?</button>\s*'
)
content = btn_how_it_works.sub('', content)

# Also let's clean up any double dividers if they exist, or just leave it clean
# Look at the sidebar nav part of index_sidebar_utf8.blade.php:
#         <hr class="sidebar-divider">
#         <div class="sidebar-section-label">App</div>
#         <button class="sidebar-btn" onclick="switchTab(event, 'tab-price-plan')">
#             <span class="icon">💎</span> Price Plan
#         </button>
#         <button class="sidebar-btn" onclick="switchTab(event, 'tab-how-it-works')">
#             <span class="icon">📖</span> How It Works
#         </button>
# 
#         <hr class="sidebar-divider">
#         <button class="sidebar-btn" onclick="switchTab(event, 'tab-settings')">
#             <span class="icon">⚙️</span> Settings
#         </button>
#
# Since we removed both buttons, we have:
#         <hr class="sidebar-divider">
#         <div class="sidebar-section-label">App</div>
#         
#         
# 
#         <hr class="sidebar-divider">
#         <button class="sidebar-btn" onclick="switchTab(event, 'tab-settings')">
#
# Let's clean that section up to make it beautiful:
sidebar_nav_block = """        <hr class="sidebar-divider">
        <div class="sidebar-section-label">App</div>
        
        
 

        <hr class="sidebar-divider">
        <button class="sidebar-btn" onclick="switchTab(event, 'tab-settings')">"""

cleaned_sidebar_nav_block = """        <hr class="sidebar-divider">
        <div class="sidebar-section-label">App</div>
        <button class="sidebar-btn" onclick="switchTab(event, 'tab-settings')">"""

content = content.replace(sidebar_nav_block, cleaned_sidebar_nav_block)
# Also try replacing with normalized newlines
content = re.sub(
    r'<hr class="sidebar-divider">\s*<div class="sidebar-section-label">App</div>\s*<hr class="sidebar-divider">\s*(?=<button class="sidebar-btn" onclick="switchTab\(event, \'tab-settings\'\)">)',
    r'<hr class="sidebar-divider">\n        <div class="sidebar-section-label">App</div>\n        ',
    content
)

# 2. Remove Price Plan and How It Works from hidden tab buttons for JS compatibility
hidden_price_plan = re.compile(
    r'<button class="tab-button"[^>]*onclick="switchTab\(event, \'tab-price-plan\'\)"[^>]*>[\s\S]*?</button>\s*'
)
content = hidden_price_plan.sub('', content)

hidden_how_it_works = re.compile(
    r'<button class="tab-button"[^>]*onclick="switchTab\(event, \'tab-how-it-works\'\)"[^>]*>[\s\S]*?</button>\s*'
)
content = hidden_how_it_works.sub('', content)

# 3. Remove the date filter toolbar
filter_toolbar = re.compile(
    r'<div class="filter-toolbar-container">[\s\S]*?</form>\s*</div>\s*'
)
content = filter_toolbar.sub('', content)

# 4. Remove the stats cards grid
# Match the stats cards grid comment and block
stats_grid = re.compile(
    r'<!-- 4 Stats Cards Grid[\s\S]*?</div>\s*</div>\s*</div>\s*</div>\s*(?=\s*<!-- Hidden tab buttons)'
)
content = stats_grid.sub('', content)

# Let's do an alternative match for stats grid if the above didn't match
if 'class="stats-grid"' in content:
    # Match non-greedily from <div class="stats-grid"> to the next <!-- Hidden tab buttons
    stats_grid_alt = re.compile(
        r'<div class="stats-grid">[\s\S]*?(?=<!-- Hidden tab buttons)'
    )
    content = stats_grid_alt.sub('', content)

# 5. Remove Tab 6 (Price Plan) and Tab 7 (How It Works) content divs
# They are: <!-- Tab 6: Price Plan --> ... <!-- Tab 7: How It Works & Benefits --> ... up to </div><!-- /.dashboard-container -->
tabs_content = re.compile(
    r'<!-- Tab 6: Price Plan -->[\s\S]*?<!-- Tab 7: How It Works & Benefits -->[\s\S]*?</div>\s*</div>\s*(?=</div><!-- /.dashboard-container -->)'
)
content = tabs_content.sub('', content)

# Let's double check if we have any other instances
# Write the cleaned file out
with open('resources/views/dashboard/index.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Cleaned dashboard index file generated successfully!")
