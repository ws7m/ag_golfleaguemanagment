# Golf League Management Software — Design Document

## 1. Overview
- **Purpose:** _What problem does this solve, and for whom?_
- **Scope:** _What's in scope for v1? What's explicitly out of scope / deferred?_

## 2. Technology & Hosting
- **Language/Framework:** PHP / CodeIgniter
- **Database:** SQLite (confirm or revise)
- **Hosting Environment:** Self-hosted on home Linux PC
- **Constraints:** _Hardware specs, network setup (port forwarding, dynamic DNS, reverse proxy?), domain/SSL plans, backup strategy_

## 3. User Roles
### 3.1 League Admin
- _Permissions / capabilities_

### 3.2 Player / Member
- _Permissions / capabilities_

### 3.3 Guest / Public (if applicable)
- _What, if anything, is visible without an account?_

## 4. Functional Requirements

### 4.1 Player Sign-Up / Registration
- _Requirements:_
- _Fields collected:_
- _Approval process (self-serve vs. admin-approved)?_
- _Open questions:_

### 4.2 Score Entry
- _Requirements:_
- _Who can enter scores (self-entry, admin-only, both)?_
- _Entry format (hole-by-hole, total, etc.)?_
- _Validation / editing / dispute rules?_
- _Open questions:_

### 4.3 Results & Leaderboards
- _Requirements:_
- _What views are needed (weekly, season-to-date, by game type)?_
- _Open questions:_

### 4.4 Handicap Calculation
- _Requirements:_
- _Which handicap method/formula?_
- _Update frequency (per round, weekly, seasonal)?_
- _Open questions:_

### 4.5 Weekly Game Format Definition
- _Requirements:_
- _What game types need to be supported (stroke play, Stableford, skins, scrambles, etc.)?_
- _Who defines the week's format, and how far in advance?_
- _How does format affect scoring/handicap calculations?_
- _Open questions:_

### 4.6 League / Season Management
- _Requirements:_
- _Seasons, divisions/flights, schedules, courses?_
- _Open questions:_

### 4.7 Admin Management Tools
- _Requirements:_
- _What does the admin dashboard need to show/do?_
- _Open questions:_

### 4.8 Notifications (if needed)
- _Requirements:_
- _Email/SMS/none? What triggers a notification?_

## 5. Non-Functional Requirements

### 5.1 Mobile Responsiveness
- _Requirements:_

### 5.2 Performance / Hardware Constraints
- _Expected concurrent users, acceptable response times_

### 5.3 Security & Access Control
- _Authentication method, session handling, admin vs. player access control_

### 5.4 Data Backup & Retention
- _Backup frequency/method, how long is historical data kept_

## 6. Data Model
_To be defined once functional requirements above are filled in._

## 7. Open Questions / Decisions Log
| Date | Question | Decision | Notes |
|------|----------|----------|-------|
|      |          |          |       |
