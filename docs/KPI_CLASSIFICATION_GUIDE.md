# KPI Classification Guide
## 3-Tier Architecture for Optimal Performance

---

## Classification Logic

### **TIER 1: REAL-TIME DASHBOARD (Cache: 1-5 minutes)**
**Criteria:**
- Requires immediate visibility for operational decisions
- Users need to take action within minutes
- Simple calculations (counts, sums, status checks)
- Operators and supervisors monitor actively

**Total: 18 KPIs**

---

### **TIER 2: SHIFT-BASED CACHED (Calculate after each shift)**
**Criteria:**
- Meaningful at shift granularity
- Used for shift comparisons and performance reviews
- Medium complexity calculations (rates, efficiencies)
- Managers review for daily operations

**Total: 28 KPIs**

---

### **TIER 3: SCHEDULED REPORTS (Daily/Weekly/Monthly)**
**Criteria:**
- Strategic/analytical insights
- Complex calculations or forecasting
- Historical trends and comparisons
- Executives use for planning and decision-making

**Total: 44 KPIs**

---

## TIER 1: REAL-TIME DASHBOARD KPIs (18 KPIs)
**Update Frequency:** 1-5 minutes | **Cache TTL:** 1-5 min | **Delivery:** Live dashboard

| # | KPI Name | Update Freq | Why Real-Time | Primary Users |
|---|----------|-------------|---------------|---------------|
| 1 | **Current Machine Status Dashboard** | 1 min | Immediate downtime response | Operators, Maintenance |
| 2 | **Real-Time Production Status** | 2 min | Monitor live output rates | Supervisors, Operators |
| 3 | **Downtime Alert System** | 1 min | Immediate issue notification | Maintenance, Supervisors |
| 4 | **Machine Utilization Rate (Current)** | 3 min | Real-time resource allocation | Supervisors, Planners |
| 5 | **Work Order Status Distribution** | 5 min | Current workload visibility | Planners, Supervisors |
| 6 | **Work Order Aging** | 5 min | Identify overdue/at-risk orders | Planners, Managers |
| 7 | **Operator Workload Distribution** | 5 min | Balance work assignments | Supervisors |
| 8 | **Schedule Adherence** | 5 min | Track timeline compliance | Planners, Supervisors |
| 9 | **Production Volume (Today)** | 5 min | Track daily targets | Supervisors, Managers |
| 10 | **Quality Issues (Active)** | 3 min | Immediate quality intervention | Quality Team, Operators |
| 11 | **Part Fulfillment Progress** | 5 min | Customer order tracking | Sales, Planners |
| 12 | **Resource Demand Forecasting (Current)** | 5 min | Immediate staffing needs | Supervisors, HR |
| 13 | **Planning Pipeline Health** | 5 min | Workflow bottleneck alerts | Planners |
| 14 | **SO to WO Rate (Today)** | 5 min | Planning conversion tracking | Planners |
| 15 | **Assignment Status (Current)** | 5 min | Order assignment monitoring | Planners, Supervisors |
| 16 | **Work Progress (Active Orders)** | 5 min | In-progress order tracking | Supervisors |
| 17 | **Timeline Pipeline (Current)** | 5 min | On-time vs delayed tracking | Planners, Managers |
| 18 | **Bottleneck Analysis (Real-Time)** | 5 min | Immediate constraint identification | Supervisors, Planners |

**Technical Implementation:**
```php
// Lightweight queries, minimal cache
Cache::store('realtime_cache')->remember($key, 300, $callback); // 1-5 min
```

---

## TIER 2: SHIFT-BASED CACHED KPIs (28 KPIs)
**Update Frequency:** After each shift ends (3x daily) | **Cache TTL:** Until next shift | **Delivery:** Dashboard + Shift reports

| # | KPI Name | Calculate When | Why Shift-Based | Primary Users |
|---|----------|----------------|-----------------|---------------|
| 1 | **Work Order Completion Rate** | After shift | Shift productivity comparison | Managers, Supervisors |
| 2 | **Production Throughput** | After shift | Shift output measurement | Managers |
| 3 | **Production Throughput Per Machine Group** | After shift | Group performance by shift | Managers, Planners |
| 4 | **Scrap Rate** | After shift | Quality control per shift | Quality Team, Managers |
| 5 | **Quality Rate by Part Number** | After shift | Part-specific quality trends | Quality Team |
| 6 | **Quality Rate by Machine** | After shift | Machine quality performance | Maintenance, Quality |
| 7 | **Quality Rate by Operator** | After shift | Individual operator performance | Supervisors, HR |
| 8 | **Quality Rate by Operator Proficiency** | After shift | Skill-based quality analysis | HR, Training |
| 9 | **Quality Rate by Machine Group** | After shift | Group quality metrics | Managers |
| 10 | **Machine Group Utilization** | After shift | Resource utilization by shift | Planners, Managers |
| 11 | **Machine WO Status Distribution** | After shift | Machine workload analysis | Planners |
| 12 | **Skill Level Distribution** | After shift | Team composition by shift | Supervisors, HR |
| 13 | **Operator Efficiency** | After shift | Individual productivity | Supervisors, HR |
| 14 | **Part Number Performance** | After shift | Part-level production metrics | Planners, Sales |
| 15 | **Cycle Time Efficiency** | After shift | Process speed analysis | Managers, Engineers |
| 16 | **Lead Time Performance** | After shift | Order fulfillment speed | Planners, Sales |
| 17 | **Shift Performance** | After shift | Overall shift metrics | Managers |
| 18 | **First Pass Yield** | After shift | Quality tracking by shift | Quality Team |
| 19 | **Setup Time Analysis** | After shift | Changeover time tracking | Engineers, Supervisors |
| 20 | **Changeover Efficiency** | After shift | Transition time optimization | Engineers |
| 21 | **Downtime Analysis** | After shift | Downtime breakdown by shift | Maintenance, Managers |
| 22 | **DownTime by Root Cause** | After shift | Issue categorization | Maintenance, Engineers |
| 23 | **Machine Utilization by Time Period** | After shift | Hourly utilization patterns | Planners |
| 24 | **Machine Availability Rate** | After shift | Equipment availability | Maintenance |
| 25 | **Planned vs. Unplanned Downtime Ratio** | After shift | Maintenance effectiveness | Maintenance Team |
| 26 | **Current Machine Performance Index** | After shift | Machine scoring | Maintenance, Managers |
| 27 | **Part Performance (Volume and Yield)** | After shift | Part production analysis | Planners, Quality |
| 28 | **Production Volume by Part Number** | After shift | Part-specific output | Planners, Sales |

**Technical Implementation:**
```php
// Scheduled job after each shift
Schedule::command('kpi:calculate-shift')
    ->dailyAt('07:00')  // After night shift
    ->dailyAt('15:00')  // After day shift
    ->dailyAt('23:00'); // After evening shift

// Store in kpi_shift_summaries table
// Cache until next shift recalculation
Cache::store('kpi_cache')->tags(['factory_X', 'shift'])->put($key, $value, $ttl);
```

---

## TIER 3: SCHEDULED REPORTS (44 KPIs)
**Update Frequency:** Daily/Weekly/Monthly | **Delivery:** PDF/Excel reports, Email | **Users:** Executives, Planners

### 3A: DAILY REPORTS (Generated at 2 AM) - 12 KPIs

| # | KPI Name | Frequency | Comparison | Recipients |
|---|----------|-----------|------------|------------|
| 1 | **On-Time Delivery** | Daily | vs Yesterday | Sales, Managers |
| 2 | **Overall Equipment Effectiveness (OEE)** | Daily | vs Yesterday | Maintenance, Ops |
| 3 | **Mean Time Between Failures (MTBF)** | Daily | Rolling 7-day | Maintenance |
| 4 | **Mean Time to Repair (MTTR)** | Daily | Rolling 7-day | Maintenance |
| 5 | **Machine Reliability Score** | Daily | vs Yesterday | Maintenance, Managers |
| 6 | **Capacity Utilization Rate** | Daily | vs Yesterday | Planners, Executives |
| 7 | **BOM Utilization Rate** | Daily | vs Yesterday | Planners |
| 8 | **BOM to Work Order Conversion Time** | Daily | Rolling average | Planners |
| 9 | **Planning Accuracy - Machine Group** | Daily | vs Yesterday | Planners |
| 10 | **Planning Accuracy - Skill Requirements** | Daily | vs Yesterday | Planners, HR |
| 11 | **Delivery Performance** | Daily | vs Yesterday | Sales, Executives |
| 12 | **Quality Rate Tracking (Aggregate)** | Daily | vs Yesterday | Quality Team |

### 3B: WEEKLY REPORTS (Generated Monday 3 AM) - 16 KPIs

| # | KPI Name | Frequency | Comparison | Recipients |
|---|----------|-----------|------------|------------|
| 1 | **Sales Order Planning Efficiency** | Weekly | vs Last Week | Sales, Planning |
| 2 | **Planning Lead Time** | Weekly | vs Last Week | Planning Team |
| 3 | **Planning Complexity Ratio** | Weekly | vs Last Week | Planning Team |
| 4 | **Sales Order Fulfillment Planning** | Weekly | vs Last Week | Sales, Planning |
| 5 | **BOM Productivity Index** | Weekly | vs Last Week | Planning, Ops |
| 6 | **Work Order Planning Adherence** | Weekly | vs Last Week | Planning, Ops |
| 7 | **Multi-BOM Order Coordination** | Weekly | vs Last Week | Planning Team |
| 8 | **Planning vs. Execution Timeline** | Weekly | vs Last Week | Planning, Ops |
| 9 | **End-to-End Order Fulfillment Time** | Weekly | vs Last Week | Sales, Ops |
| 10 | **Planning Hub Efficiency Score** | Weekly | vs Last Week | Planning Team |
| 11 | **Resource Planning Optimization** | Weekly | vs Last Week | Planning, HR |
| 12 | **Sales Order Delivery Performance** | Weekly | vs Last Week | Sales, Executives |
| 13 | **Order Complexity Impact** | Weekly | vs Last Week | Planning, Sales |
| 14 | **Customer Order Predictability** | Weekly | vs Last Week | Sales, Planning |
| 15 | **Operational Work Order Aging (Trends)** | Weekly | vs Last Week | Managers |
| 16 | **Customer Performance** | Weekly | vs Last Week | Sales, Executives |

### 3C: MONTHLY REPORTS (Generated 1st at 4 AM) - 16 KPIs

| # | KPI Name | Frequency | Comparison | Recipients |
|---|----------|-----------|------------|------------|
| 1 | **Planning Hub Learning Curve** | Monthly | vs Last Month | Planning Team |
| 2 | **Bottleneck Prediction Accuracy** | Monthly | vs Last Month | Planning, Ops |
| 3 | **Cross-Order Resource Optimization** | Monthly | vs Last Month | Planning, Ops |
| 4 | **Planning Pipeline Health (Trends)** | Monthly | vs Last Month | Planning, Executives |
| 5 | **Resource Demand Forecasting (Accuracy)** | Monthly | vs Last Month | Planning, HR |
| 6 | **Planning Hub Alert System (Summary)** | Monthly | Count/trends | Planning, Ops |
| 7 | **Overall Machine Performance Trends** | Monthly | vs Last Month | Maintenance, Executives |
| 8 | **Operator Training Effectiveness** | Monthly | vs Last Month | HR, Training |
| 9 | **Part Number Performance Trends** | Monthly | vs Last Month | Sales, Planning |
| 10 | **Customer Satisfaction Metrics** | Monthly | vs Last Month | Sales, Executives |
| 11 | **Production Capacity Trends** | Monthly | vs Last Month | Planning, Executives |
| 12 | **Quality Cost Analysis** | Monthly | vs Last Month | Quality, Finance |
| 13 | **Maintenance Cost Analysis** | Monthly | vs Last Month | Maintenance, Finance |
| 14 | **Labor Efficiency Trends** | Monthly | vs Last Month | HR, Finance |
| 15 | **Equipment Investment ROI** | Monthly | vs Last Month | Finance, Executives |
| 16 | **Strategic Performance Dashboard** | Monthly | vs Last Month | Executives, Owners |

**Technical Implementation:**
```php
// Laravel Scheduler for reports
Schedule::command('reports:generate daily')->dailyAt('02:00');
Schedule::command('reports:generate weekly')->weeklyOn(1, '03:00');
Schedule::command('reports:generate monthly')->monthlyOn(1, '04:00');

// Queue-based generation (non-blocking)
dispatch(new GenerateDailyReport($factory, $date))->onQueue('reports');

// Store in storage and email
Mail::to($recipients)->send(new KPIReport($pdf, $excel));
```

---

## Summary Table

| Tier | KPI Count | Update Method | Cache Strategy | Query Load | Users |
|------|-----------|---------------|----------------|------------|-------|
| **Tier 1** | 18 | Live calculation | Redis 1-5 min | High frequency, simple | Operators, Supervisors |
| **Tier 2** | 28 | Shift-end job | Redis + summary table | Medium frequency, medium complexity | Managers, Planners |
| **Tier 3** | 44 | Scheduled reports | Pre-generated files | Low frequency, complex | Executives, Planning |
| **TOTAL** | **90** | - | - | - | - |

---

## Performance Impact

### Without Optimization (All Real-Time)
- **Dashboard load:** 15-30 seconds
- **Queries per page:** 400-600
- **Concurrent users:** 10-15 max
- **Server load:** Very High ❌

### With 3-Tier Architecture
- **Dashboard load:** <1 second
- **Queries per page:** 10-30
- **Concurrent users:** 100+ per factory
- **Server load:** Low ✅

### Query Reduction by Tier

| Scenario | Tier 1 (Real-Time) | Tier 2 (Shift) | Tier 3 (Report) | Total |
|----------|-------------------|----------------|-----------------|-------|
| **Naive approach** | 18 × 2 = 36 | 28 × 2 = 56 | 44 × 2 = 88 | **180 queries** |
| **Optimized** | 18 queries | 28 table reads | 0 (pre-generated) | **46 queries** |
| **With cache (95% hit)** | ~1 query | ~1 query | 0 | **~2 queries** |

---

## Role-Based KPI Access

### Operators (5-8 KPIs from Tier 1)
- Current Machine Status
- Work Order Status (Assigned to them)
- Real-Time Production Status
- Quality Issues (Active)
- Personal Efficiency (from Tier 2)

### Supervisors (15-25 KPIs from Tier 1 & 2)
- All Tier 1 KPIs
- Shift Performance KPIs
- Operator/Machine efficiency
- Daily reports summary

### Managers (30-50 KPIs from Tier 1, 2 & 3)
- All Tier 1 & 2 KPIs
- Daily and Weekly reports
- Comparative analytics

### Executives/Owners (All 90 KPIs)
- Dashboard summaries
- All scheduled reports
- Strategic insights
- Monthly analytics

---

## Implementation Priority

### Phase 1: Foundation (Week 1-2)
- Setup Redis cache
- Create summary tables
- Build base report infrastructure

### Phase 2: Tier 1 - Real-Time (Week 3)
- Implement 18 real-time KPIs
- Auto-refresh dashboard
- Role-based filtering

### Phase 3: Tier 2 - Shift-Based (Week 4)
- Build shift calculation jobs
- Populate summary tables
- Create shift comparison views

### Phase 4: Tier 3 - Reports (Week 5-6)
- Build report generators
- Setup email delivery
- Create report archive

---

## Cache Invalidation Strategy

### Tier 1 (Real-Time)
- **Auto-expire:** 1-5 minutes
- **Manual flush:** On critical data updates
- **Tags:** `['factory_X', 'realtime']`

### Tier 2 (Shift-Based)
- **Auto-expire:** On shift change
- **Manual flush:** After shift calculation job
- **Tags:** `['factory_X', 'shift', 'date_YYYY-MM-DD']`

### Tier 3 (Reports)
- **Auto-expire:** N/A (files don't expire)
- **Regenerate:** On schedule only
- **Storage:** `storage/app/reports/`

---

## Multi-Tenancy Considerations

### Cache Isolation
```php
// Factory-specific cache namespacing
Cache::tags(["factory_{$factoryId}", "tier_1"])->remember($key, $ttl, $callback);
```

### Summary Table Partitioning
```sql
-- All summary tables indexed by factory_id
CREATE INDEX idx_factory_date ON kpi_shift_summaries(factory_id, shift_date);
```

### Report Generation
```php
// Per-factory scheduled jobs
foreach (Factory::all() as $factory) {
    dispatch(new GenerateDailyReport($factory))->onQueue('reports');
}
```

---

## Monitoring & Alerts

### Performance Metrics to Track
- Cache hit rate per tier (target: >95%)
- Dashboard load time per role (target: <1s)
- Report generation time (target: <5 min)
- Query count per page (target: <30)

### Alerts
- Shift calculation job failures
- Report generation failures
- Cache hit rate drops below 90%
- Dashboard load time exceeds 2s

---

## Next Steps

1. **Review this classification** with your team
2. **Adjust KPI tiers** based on business priorities
3. **Approve implementation roadmap**
4. **Begin Phase 1** (Redis + Summary tables)

---

**Document Version:** 1.0
**Last Updated:** October 8, 2025
**Author:** KPI Optimization Team
