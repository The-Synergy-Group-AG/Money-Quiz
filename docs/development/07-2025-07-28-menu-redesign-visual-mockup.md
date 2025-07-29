# Money Quiz Menu Visual Mockup

## Dashboard Overview Mockup

```
┌─────────────────────────────────────────────────────────────────────────┐
│ WordPress Admin                                              👤 Admin     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│  💰 Money Quiz  >  Dashboard  >  Overview                                │
│ ─────────────────────────────────────────────────────────────────────── │
│                                                                           │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ Dashboard Overview                          [ View Reports ] primary │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                           │
│ ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐         │
│ │ 📊 Active Quizzes│ │ 👥 Total Leads   │ │ 📈 Completion Rate│        │
│ │                  │ │                  │ │                  │         │
│ │       12         │ │      1,847       │ │      73.5%       │         │
│ │   +2 this week   │ │  +124 this week  │ │   ↑ 5.2%         │         │
│ └──────────────────┘ └──────────────────┘ └──────────────────┘         │
│                                                                           │
│ ┌─────────────────────────────────┐ ┌───────────────────────────────┐   │
│ │ 🎯 Quick Actions                 │ │ 📈 Recent Activity            │   │
│ │                                 │ │                               │   │
│ │ [+ New Quiz]  [View Results]   │ │ • John completed Money Quiz   │   │
│ │ [Questions]   [Email Campaign] │ │ • Sarah viewed results        │   │
│ │ [Settings]    [Export Data]    │ │ • New prospect: Mike Chen     │   │
│ └─────────────────────────────────┘ │ • Quiz "Personality" edited   │   │
│                                     │ • 5 new leads today           │   │
│                                     └───────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

## Quiz Management Page Mockup

```
┌─────────────────────────────────────────────────────────────────────────┐
│  💰 Money Quiz  >  Quizzes  >  All Quizzes                              │
│ ─────────────────────────────────────────────────────────────────────── │
│                                                                           │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ All Quizzes                                    [ + Add New ] primary │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                           │
│ [ Search quizzes... 🔍 ]          Filter: [All ▼] [Active ▼] [Date ▼]  │
│                                                                           │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ □ | Quiz Title          | Questions | Responses | Status  | Actions  │ │
│ ├─────────────────────────────────────────────────────────────────────┤ │
│ │ □ | Money Personality   |    15     |   1,234   | Active  | Edit •••│ │
│ │ □ | Financial Archetype |    20     |     567   | Active  | Edit •••│ │
│ │ □ | Wealth Mindset      |    10     |     890   | Draft   | Edit •••│ │
│ │ □ | Investment Style    |    12     |     234   | Active  | Edit •••│ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                           │
│ Bulk Actions: [Delete ▼] [Apply]                      < 1 2 3 ... 10 >  │
└─────────────────────────────────────────────────────────────────────────┘
```

## Navigation States

### Sidebar Menu (Collapsed)
```
┌────┐
│ 💰 │ Money Quiz
├────┤
│ 📊 │ Dashboard
├────┤
│ 🎯 │ Quizzes
├────┤
│ 👥 │ Audience
├────┤
│ 📢 │ Marketing
├────┤
│ ⚙️ │ Settings
└────┘
```

### Sidebar Menu (Expanded - Quizzes Section)
```
┌─────────────────┐
│ 💰 Money Quiz   │
├─────────────────┤
│ 📊 Dashboard    │
├─────────────────┤
│ 🎯 Quizzes     ▼│
│   All Quizzes   │
│   Add New       │
│   Questions     │
│   Archetypes    │
│   Templates     │
├─────────────────┤
│ 👥 Audience     │
├─────────────────┤
│ 📢 Marketing    │
├─────────────────┤
│ ⚙️ Settings     │
└─────────────────┘
```

## Color Coding System

```
Dashboard:  ███ #0073aa (WordPress Blue)
Quizzes:    ███ #46b450 (Success Green)
Audience:   ███ #826eb4 (Royal Purple)
Marketing:  ███ #ff6900 (Vibrant Orange)
Settings:   ███ #555d66 (Neutral Gray)
```

## Mobile Responsive View

```
┌─────────────────────┐
│ ☰ Money Quiz       │
├─────────────────────┤
│ Dashboard Overview  │
├─────────────────────┤
│ 📊 Active: 12       │
│ 👥 Leads: 1,847     │
│ 📈 Rate: 73.5%      │
├─────────────────────┤
│ Quick Actions:      │
│ [+ New Quiz]        │
│ [View Results]      │
│ [Export Data]       │
└─────────────────────┘
```

## Interactive Elements

### Hover States
- Menu items: Background color lightens, text color darkens
- Cards: Subtle shadow elevation
- Buttons: Darker shade of primary color
- Links: Underline appears

### Active/Current States
- Current menu: Bold text, colored left border
- Active tab: White background, connected to content
- Selected items: Checkbox checked, row highlighted

### Loading States
- Spinner overlay on content areas
- Skeleton screens for data tables
- Progress bars for long operations

## Accessibility Features

1. **Keyboard Navigation**
   - Tab through all interactive elements
   - Arrow keys for menu navigation
   - Escape to close dropdowns
   - Enter/Space to activate buttons

2. **Screen Reader Support**
   - Proper ARIA labels
   - Landmark regions
   - Status announcements
   - Form field descriptions

3. **Visual Indicators**
   - Focus rings on all interactive elements
   - High contrast mode support
   - Color-blind friendly palette
   - Text alternatives for icons