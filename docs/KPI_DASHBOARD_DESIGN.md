# KPI Analytics Dashboard Design - Scalable Architecture for 100+ KPIs

## Problem Statement
With approximately 100 KPIs to manage, displaying all KPIs on a single page is not practical. We need a scalable, user-friendly navigation structure.

## Proposed Solution: Multi-Level Navigation with Customization

### 1. **KPI Analytics Hub (Main Landing Page)**
The main KPI Analytics Dashboard becomes a **hub page** that provides:
- Quick access to favorited/pinned KPIs (top 5-10)
- Category overview cards with KPI counts
- Search functionality
- Recent KPI views history

### 2. **Category-Based Sub-Pages**
Based on the KPI Classification Guide, create separate pages for each major category:

#### Operational KPIs Page
- `/admin/{tenant}/kpi/operational`
- Displays all operational KPIs in an organized grid
- Sub-categories: Machine Status, Production Flow, Scheduling

#### Quality KPIs Page
- `/admin/{tenant}/kpi/quality`
- Quality metrics and defect analysis
- Sub-categories: Defect Rate, First Pass Yield, Quality Control

#### Production KPIs Page
- `/admin/{tenant}/kpi/production`
- Production throughput and efficiency
- Sub-categories: Throughput, OEE, Downtime

#### Workforce KPIs Page
- `/admin/{tenant}/kpi/workforce`
- Operator and labor metrics
- Sub-categories: Operator Performance, Labor Efficiency

#### Inventory KPIs Page
- `/admin/{tenant}/kpi/inventory`
- Material and inventory tracking
- Sub-categories: Material Usage, Stock Levels, WIP

#### Financial KPIs Page
- `/admin/{tenant}/kpi/financial`
- Cost and revenue metrics
- Sub-categories: Production Costs, Revenue per Unit

### 3. **Individual KPI Detail Pages**
Each KPI gets its own detail page:
- `/admin/{tenant}/kpi/{category}/{kpi-slug}`
- Full dual-mode functionality (Dashboard + Analytics)
- Advanced filtering and comparison
- Export capabilities
- Historical trend charts

### 4. **Navigation Structure**

```
├── Dashboard (Main)
│   └── [KPI Analytics Link Widget]
│
├── KPI System (Navigation Group)
│   ├── KPI Analytics Hub ⭐ (Main landing)
│   ├── My Favorites (Quick access to pinned KPIs)
│   ├── Operational KPIs
│   │   ├── Machine Status
│   │   ├── Work Order Status
│   │   ├── Production Schedule
│   │   └── ...
│   ├── Quality KPIs
│   │   ├── Defect Rate
│   │   ├── First Pass Yield
│   │   └── ...
│   ├── Production KPIs
│   │   ├── Throughput per Machine
│   │   ├── OEE
│   │   └── ...
│   ├── Workforce KPIs
│   │   ├── Operator Performance
│   │   ├── Labor Efficiency
│   │   └── ...
│   ├── Inventory KPIs
│   │   └── ...
│   └── Financial KPIs
│       └── ...
```

### 5. **KPI Analytics Hub Features**

#### A. Favorites Section
- Users can "pin" up to 10 KPIs
- Shows mini-cards with key metrics
- Quick toggle between Dashboard/Analytics modes
- Stored per user in database

#### B. Category Overview Cards
```
┌─────────────────────────────┐
│  Operational KPIs           │
│  15 Total | 3 Favorites     │
│  [View All →]               │
└─────────────────────────────┘
```

#### C. Search & Filter
- Search KPIs by name/description
- Filter by:
  - Category
  - Tier (1, 2, 3)
  - Status (Active, Needs Data, etc.)
  - Favorited status
- Search results show mini KPI cards

#### D. Recent Activity
- Last 5 KPIs viewed by user
- Quick access links

### 6. **Category Page Layout**

Each category page displays KPIs in a grid:

```
┌────────────────────────────────────────────────┐
│  Operational KPIs                              │
│  [Search] [Filter: All Tiers ▼] [+ Add to Fav]│
├────────────────────────────────────────────────┤
│                                                │
│  ┌──────────────┐  ┌──────────────┐          │
│  │ Machine      │  │ Work Order   │          │
│  │ Status       │  │ Status       │          │
│  │ ⭐ Running: 8│  │ Start: 12    │          │
│  │ [View →]     │  │ [View →]     │          │
│  └──────────────┘  └──────────────┘          │
│                                                │
│  [Show Analytics] [Export Data]               │
└────────────────────────────────────────────────┘
```

### 7. **Database Schema for Favorites**

```sql
CREATE TABLE kpi_user_favorites (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    factory_id BIGINT UNSIGNED NOT NULL,
    kpi_identifier VARCHAR(100) NOT NULL, -- e.g., 'machine_status'
    kpi_category ENUM('operational', 'quality', 'production', 'workforce', 'inventory', 'financial'),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_user_kpi (user_id, factory_id, kpi_identifier),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE
);

CREATE TABLE kpi_view_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    factory_id BIGINT UNSIGNED NOT NULL,
    kpi_identifier VARCHAR(100) NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (factory_id) REFERENCES factories(id) ON DELETE CASCADE,
    INDEX idx_user_viewed (user_id, factory_id, viewed_at)
);
```

### 8. **KPI Registry System**

Create a centralized KPI registry to manage all KPIs:

```php
// app/Services/KPI/KPIRegistry.php
class KPIRegistry
{
    public static function getAllKPIs(): array
    {
        return [
            'operational' => [
                [
                    'id' => 'machine_status',
                    'name' => 'Machine Status',
                    'description' => 'Real-time machine status dashboard',
                    'tier' => 1,
                    'service' => RealTimeKPIService::class,
                    'page' => MachineStatusPage::class,
                    'icon' => 'heroicon-o-cpu-chip',
                ],
                // ... more operational KPIs
            ],
            'quality' => [
                // quality KPIs
            ],
            // ... other categories
        ];
    }

    public static function getKPIsByCategory(string $category): array;
    public static function getKPIsByTier(int $tier): array;
    public static function searchKPIs(string $query): array;
}
```

### 9. **Implementation Priority**

#### Phase 1 - Core Structure (Current Sprint)
1. ✅ Machine Status KPI (already implemented)
2. Create KPI Analytics Hub page
3. Create KPI Registry system
4. Add favorites database table
5. Implement category pages (Operational first)

#### Phase 2 - Enhanced Features
1. Add search and filter functionality
2. Implement user favorites/pinning
3. Add view history tracking
4. Create 5-10 more high-priority KPIs

#### Phase 3 - Complete Rollout
1. Implement remaining ~90 KPIs
2. Add export capabilities
3. Add advanced analytics (comparisons, trends)
4. Performance optimization

### 10. **User Workflow Examples**

#### Workflow A: Quick Check (Power User)
1. User logs in → Dashboard
2. Clicks "KPI Analytics" link
3. Sees Hub with their 5 favorite KPIs already displayed
4. Quickly scans metrics, done in 30 seconds

#### Workflow B: Deep Dive (Manager)
1. User logs in → Dashboard
2. Navigates to "KPI System" → "Production KPIs"
3. Clicks on "OEE by Machine"
4. Switches to Analytics mode
5. Applies filters: Last 30 days, compare to previous period
6. Exports data for report

#### Workflow C: Discovery (New User)
1. User logs in → Dashboard
2. Clicks "KPI Analytics" link
3. Uses search: "defect"
4. Finds "Defect Rate by Part Number"
5. Clicks "View"
6. Pins to favorites for future

### 11. **Mobile Responsiveness**
- Hub page: Stack cards vertically on mobile
- Category pages: Single column KPI grid
- Individual KPI pages: Collapsible filters
- Touch-friendly favorite toggle buttons

### 12. **Performance Considerations**
- Lazy load KPI data (don't calculate all 100 on page load)
- Cache favorite KPIs for 60 seconds
- Load category pages progressively
- Implement pagination for large KPI lists (>20 per category)
- **Manual refresh for Dashboard Mode**: Users control when to fetch fresh data, reducing server load and eliminating unnecessary background polling

## Benefits of This Design

1. **Scalability**: Easily add 100+ KPIs without cluttering UI
2. **Discoverability**: Search and categorization help users find KPIs
3. **Personalization**: Favorites allow users to customize their view
4. **Performance**: Lazy loading prevents overwhelming the system
5. **Maintainability**: Registry system makes KPI management easier
6. **User Experience**: Multiple workflows accommodate different use cases

## Next Steps

1. Update current KPI Analytics Dashboard to become the Hub
2. Create KPI Registry service
3. Build category pages starting with Operational
4. Implement favorites system
5. Add search functionality
