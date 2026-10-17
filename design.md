# AG Golf League Management Software — Design Document

## 1. Overview
This golf league management software will manage a public 9-hole golf league. The league plays at an 18-hole course; each round, a group plays either the front nine (holes 1–9) or the back nine (holes 10–18), alternating week to week.

### 1.1 Scope and phases
- **Phase 1 (MVP):** courses/tees/holes, games, leagues, registration, members and flights, tee times, groups, rounds (start/close/finalize), special event rounds at other courses, game voting, online hole-by-hole scoring, admin score entry/edit, payouts and skins, handicap calculation, league home page, round detail page, member page, notifications (web + email), shared member login with member ID, individual admin login/reset, full history retention.
- **Phase 2:** scorecard picture upload by players, member-to-member messaging, season standings/reports.
- **Phase 3:** score submission by email (requires reading a league mailbox).
- **Out of scope:** online payment collection, official GHIN handicap posting, more than one course within a single round, importing tee times from the course (D24).

Items marked **D#** refer to the Decisions Log in section 11. Decided items are written into the text below with their D# for traceability. For any item still Open, the proposed default in the log is used until the decision is made.

## 2. What I want to be able to do, store and provide

### 2.1 Courses
- As admin, I want to be able to create/update/remove a golf course: name, address, phone, website, notes.
  - As admin, I want to be able to store golf course pictures, including a scorecard picture.
  - As admin, under the golf course I want to be able to create/update tees (color/name, plus Course Rating and Slope Rating for the front nine and for the back nine). Ratings are required for handicap calculation.
  - As admin, under the golf course I want to be able to create/update holes 1 to 18 with par, hole handicap (stroke index), and yardage for each tee.

### 2.2 Games
- As admin, I want to be able to create/update/remove golf games. Each game has a name, description, game type (which selects the scoring function), team/individual flag, "counts toward handicap" flag, handicap percentage, whether flights are used, whether skins are active, and the payout table used.

### 2.3 Leagues, members and registration
- As admin, I want to be able to create a league, storing the name, start and end dates, default entry fee, and the home golf course the members normally play at.
  - Regular rounds are played at the home course. Special event rounds can be played at a different course (section 2.9).
  - A member record is global and carries over between leagues (seasons). Existing members join a new league by re-registering, which creates a new league membership for them.
- As a user, I want to be able to register for the league, which either creates a member record or updates an existing member record and creates the league membership. A new member is shown (and emailed) their member ID when registration completes; a returning member keeps their existing ID.
  - Registration is open (no admin approval), but the member must verify their email address before registration completes. Admin can deactivate a member at any time. (D1)
  - Member IDs are random, non-sequential 5-digit numbers, assigned at first registration and kept across seasons. Admin can regenerate a member's ID. (D25)
  - A new member's starting handicap is entered by an admin after they register. Until it is set, the member can't be assigned a flight or play in a round. (D21)
- As admin, I want to be able to create/update/remove members (players). I can create multiple admins as well. Admins are players with extra abilities to manage the league.
- Members are assigned a flight (1, 2, or 3) based on their handicap:
  - Flight 1: handicap 5.0 and lower
  - Flight 2: handicap 5.1 to 10.0
  - Flight 3: handicap above 10.0
  - Flights are balanced so each flight has at least 15 players when there are 45 or more players: sort players by handicap, assign by the cutoffs above, then move boundary players into neighboring flights until each flight has at least 15. (D2)
  - Flights are recalculated before each round starts and then frozen for that round (the flight is copied onto the player's score record). (D2)

### 2.4 Tee times and groups
- As admin, once the league is created, I want to be able to create/update a list of tee times for the front nine and a list for the back nine. These lists form the league's default tee time set, used by regular rounds.
- The admin coordinates tee times with the golf course outside the software and enters them manually. There is no import. (D24)
- As admin, I want to be able to create additional tee time sets (for example, for a special event at another course) and assign one to a specific round instead of the default.
- As admin, I need the ability to easily adjust tee times, for example by applying an offset to all of them to account for daylight availability.
- As admin, I want to be able to create/update/remove groups. Groups line up with tee times and define which members play together and when. Groups do not always define teams.
- As admin, for each round I need to be able to switch groups to the opposite nine. Groups play the front nine one week and the back nine the next.
- As a member, I mark myself as "playing" for each round before it starts. Only players marked as playing are put into groups and get score records. (D3)
- Groups are 4 players. Admin assigns players to groups, with an auto-fill option that fills groups from the players marked as playing; admin can then adjust. (D3)

### 2.5 Rounds
- As admin, once the league is created, I want to be able to create rounds. There is one round for each day the league plays. A round defines the date, the course (defaults to the league's home course), the tee time set (defaults to the league's default set), the game to be played (or the games players can vote on, section 2.10), and the entry fee per player (defaults to the league entry fee, $10).
- As admin, I need to be able to start a round so it is active. Starting a round creates a score record for every player in the round with all hole scores empty (not yet entered). Empty is stored as NULL, never 0.
- As admin, I need to be able to close a round, which disables online scoring.
  - I need to be able to view/edit scores.
  - For team games, I need to be able to adjust team members after the round is complete and before scoring computation begins.
- As admin, I need to be able to finalize a closed round, which:
  - Calculates scores based on the game's scoring rules
  - Applies handicaps as defined by the game's scoring rules
  - Determines placement (1, 2, 3, etc.) using the tie rules in section 2.8
  - Determines skins, if skins are active
  - Updates score records with placement and winnings
  - Updates player handicaps, if the game counts toward handicap
- Any player without a complete score at finalization is marked by the admin as a no-show. No-shows are excluded from placement, and their entry fee is not included in the pot. (D19)
- As admin, I can reopen a finalized round. Reopening voids that round's placements, payouts and handicap updates (they are kept in history marked as voided, not deleted); finalizing again computes new ones. (D18)
- As admin, I can cancel a round (e.g. rainout). Entry fees may not have been collected; any fees that were collected are credited to each player for the next round. (D20)
- See section 5 for the full round lifecycle.

### 2.6 Notifications and messages
- As admin, I want to be able to create/update/remove notifications, which are shown on the league main web page and emailed to all members.
- As a user, I want to be able to send other users messages and read messages sent to me. (Phase 2)

### 2.7 Player and guest pages
- As a guest (non-member), I want to be able to view the league main web page. Guests never see member email addresses or phone numbers.
- As a user, I log in with the league's shared login ID and password, then enter my unique member ID number to access my own data or enter my score (section 9). I can switch to a different member ID without logging out, for example when entering scores for my group.
- As a user, I want to be able to go to the league home web page and see information about the league, including notifications and a list of rounds with the status of each.
- As a user, I want to be able to go to a detail web page for a round and view its details, scoring if it has been played, placements and winnings.
- As a user, for the active round, I want to be able to enter my score online. Scoring is always hole by hole, for every hole played. The score entry page must work well on a phone.
  - During an active round, a player can enter their own score, and anyone in the same group can enter scores for the whole group. Admins can edit any score. (D4)
- As a user, I want to be able to submit a scorecard picture if I do not use online scoring. An admin then enters the scores. (Phase 2)
- As a user, I want to be able to submit my round scores via email. (Phase 3) (D5)
  - The email can contain either a scorecard picture or a list of holes with the score for each hole.
  - Emails are sent to a league mailbox, which the application checks periodically. The email must include the player's member ID so the score can be matched to them.
  - Emailed scores go into an admin review queue. For a hole-by-hole list, the admin confirms the parsed scores; for a picture, the admin enters the scores from it (same as an uploaded scorecard picture). Emailed scores are only accepted while the round is Active or Closed.
- As a user, I want to be able to go to my own member web page, where I can view the rounds I played in, my placement and winnings for each, and my total winnings.

### 2.8 Payouts, ties and money
- Each player pays the round entry fee (default $10) into the pot. Admin tracks payment with a "paid" checkbox per player per round. A credit from a cancelled round (D20) counts as paid. (D6)
- All money is stored as integer cents. Payouts are rounded down to whole dollars; the leftover goes to the 1st place payout in that flight (or to the 1st place team in team games). (D7)
- **Flight games (individual stroke scoring):** the pot is divided among flights in proportion to the number of players in each flight. (D8)
  - For tick, tack, toe, the points pool is taken from the whole pot first, and the remainder is divided among flights. (D30)
- Within each flight, places 1 to 15 are paid from the flight pot using the admin-editable placement table below (the percentages add up to 100%):

  | Place | % | Place | % | Place | % |
  |---|---|---|---|---|---|
  | 1 | 11.0 | 6 | 8.5 | 11 | 6.0 |
  | 2 | 10.5 | 7 | 8.0 | 12 | 3.0 |
  | 3 | 10.0 | 8 | 7.5 | 13 | 2.0 |
  | 4 | 9.5 | 9 | 7.0 | 14 | 1.0 |
  | 5 | 9.0 | 10 | 6.5 | 15 | 0.5 |

- When a flight has fewer than 15 players, only the places that exist are paid, and their percentages are rescaled to add up to 100%. (D9)
- Ties in placement: tied players split the combined percentages of the places they occupy. For example, two players tied for 2nd each get (10.5% + 10.0%) ÷ 2. (D10)
- **Skins** (when active): $5 per hole ($45 for nine holes) is taken out of each flight pot before placement payouts and paid as skins. The amount is fixed per hole regardless of flight size, and is admin-editable on the game. (D11)
  - Within the flight, a skin is won by the single lowest gross score on a hole.
  - If the hole is tied, the skin carries over and is added to the next hole. It can carry over multiple holes, so the 9th hole skin could be worth $45.
  - A skin not won by the end of nine holes returns to the flight pot and is paid out by placement.
- **Team games (e.g. scramble):** there are no flights. The whole pot is paid to teams by placement using an admin-editable team payout table. Each team's winnings are divided evenly among its members.

### 2.9 Special events
- As admin, I want to be able to create a special event round, where the league plays a course other than its home course. A special event round has an event name, its course, and its own tee time set.
- The course must be set up like any other course (tees, ratings, holes, yardages) so scoring and handicaps work.
- Which holes are played at a special event (front nine, back nine, a 9-hole course, or 18 holes) is chosen on the round. The front/back alternation of groups does not apply to special event rounds, and a special event does not change the alternation for the next regular round.
- Special event rounds have no special rules for handicap or payouts: they follow the settings of the game being played, like any other round. (D26)

### 2.10 Game voting
- As admin, for each round I want to be able to choose a list of candidate games, and set when voting opens and closes.
- As a member, I want to be able to log in and vote for the game I would like to play in an upcoming round. One vote per member per round; I can change my vote until voting closes.
- As a member, I want to see the current vote counts for a round. Counts are shown live while voting is open. (D28)
- When voting closes, the game with the most votes becomes the round's game. If there is a tie, the admin picks the game. The admin may also override the voting result; overrides are recorded in the audit log. (D27)
- Voting must be closed and the round's game set before the round can be started.
- All active members of the league may vote, whether or not they are playing that round. (D29)

### 2.11 Historical data
- All league data is kept for historical purposes, including leagues, rounds, games, courses, players, groups, teams, scores, placements, payouts, skins, handicap history and votes.
- "Remove" in this document means **archive**: the record is hidden from lists and can't be chosen for new rounds, but it stays in the database and still appears in historical pages. League data is never hard-deleted from the application.
- Results must not change when reference data changes later. When a round is finalized, the values used to compute it (course rating, slope, par, hole handicaps, handicap index, handicap percentage, payout percentages, entry fee) are stored with the round's scores and payouts, so editing a course, game or payout table only affects future rounds.
- Players can view past leagues (seasons) and their results.

## 3. Game definitions
- Some game types apply a percentage of the player's handicap. The game specifies the percentage.
- Some team game types average the team members' handicaps and apply a percentage of that average as the team handicap. The game specifies the percentage.
- Each game references a payout table, so games define how winnings are split by placement.
- Because scoring rules are complex, each game type maps to a scoring function in code. Finalizing a round calls that function to process each score, apply handicaps and calculate placement.
- Net score = total gross score for the nine minus the applied handicap, unless the game says otherwise. Handicap strokes are applied to the total for placement; they are allocated hole by hole only to compute net double bogey for handicap purposes. (D12)

Games to be supported:

| Game | Scoring | Handicap applied | Counts toward handicap | Payout |
|---|---|---|---|---|
| Stroke play | Individual, per hole | 100% of player handicap | Yes | By flight, by place |
| Stroke play with skins | Individual, per hole | 100% of player handicap | Yes | By flight, by place, plus skins |
| Scramble | One team score per hole | % of averaged team handicap | No | Team payout table, no flights |
| Modified best ball | Individual, per hole | % of player handicap | No | By flight, by place |
| Red, white, blue | Individual, per hole; tee recorded per hole | % of player handicap | No | By flight, by place |
| Modified red, white, blue | Individual, per hole; tee recorded per hole | % of player handicap | No | By flight, by place |
| Irons only | Individual, per hole | 100% of player handicap | No | By flight, by place |
| 3 clubs only | Individual, per hole | 100% of player handicap | No | By flight, by place |
| Tick, tack, toe | Individual, per hole, plus tick/tack/toe flags | 100% of player handicap | Yes | By flight, by place, plus points payout |

- **Stroke play:** standard stroke play. Placement is by net score. The number of paid places depends on the number of players, typically 15.
- **Stroke play with skins:** standard stroke play, with skins played on each hole (see section 2.8).
- **Scramble:** team based. Each player hits, all players move to the best ball and hit again, continuing until holed out.
  - Team formation: by default, the team is the group you teed off with. Admin can adjust teams after the round (section 2.5).
  - Scoring: one score per hole is stored for the team. Any team member may enter it; all members see the same score.
  - Teams have 2 to 4 players. The team handicap percentage is set on the game. (D14)
- **Modified best ball:** standard stroke play, except each player may move their ball to the best ball once per hole. The rest of the hole is played individually until holed out.
- **Red, white, blue:** players choose to hit from the red, white or blue tees on holes of their choice, and must play exactly 3 holes from each. The score page records the tee played on each hole and enforces 3/3/3.
- **Modified red, white, blue:** players start on their normal tee color. After a bogey or worse (D15), the player moves forward one tee; after a par they stay on the same tee; after a birdie or better they move back one tee. If already on the front or back tee, they stay there. The tee played is recorded per hole.
- **Irons only:** the entire round is played with irons only.
- **3 clubs only:** each player chooses three clubs and uses only those clubs for the entire round.
- **Tick, tack, toe:** standard stroke play, but players also record, per hole: first on the green (tick), closest to the pin (tack), and first in the hole (toe), on the scoring page or scorecard.
  - Each tick, tack and toe is worth 1 point. Each player is paid the payout per point for every point they earned, in addition to their placement payout. (D13)
  - A fixed amount per player, set on the game by the admin, is taken from the pot into a points pool for the whole field (not per flight). Payout per point = points pool ÷ total points earned by all players. (D30)

## 4. Technology & Hosting
- **Language/Framework:** PHP 8.2+ / CodeIgniter 4.x
- **Database:** SQLite in WAL mode. This is sufficient for the expected load (section 8). Revisit only if write contention appears.
- **Front end:** server-rendered pages, mobile-first layout (score entry happens on the course on phones).
- **Hosting Environment:** self-hosted on a home Linux PC.
- **Network and SSL:** the site is published through a Cloudflare Tunnel. No router port forwarding or dynamic DNS is needed, and Cloudflare provides the SSL certificate. The domain's DNS must be managed by Cloudflare. (D16)
- **Email:** outgoing email is sent through FastMail's SMTP server (authenticated with an app password stored in the server's environment configuration, not in the code). Mail is never sent directly from the home server: residential ISPs commonly block port 25, and mail from a home IP is usually marked as spam. Notification emails to all members must stay within FastMail's sending limits (send in batches if needed). (D17)
- **File storage:** uploaded pictures are stored on disk outside the web root, with file-type checks and a size limit (proposed 10 MB).
- **Constraints:** _hardware specs, domain name_

## 5. Round Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Scheduled: admin creates round
    Scheduled --> Active: admin starts round
    Active --> Closed: admin closes round
    Closed --> Finalized: admin finalizes round
    Finalized --> Closed: admin reopens (D18)
    Scheduled --> Cancelled: admin cancels
    Active --> Cancelled: admin cancels (rainout)
```

| State | Meaning | Who can change scores | Shown to players as |
|---|---|---|---|
| Scheduled | Round created; date, course, tee time set and entry fee set; game voting may be open; groups/tee times being arranged | Nobody (no score records yet) | Upcoming (voting open/closed) |
| Active | Round is being played | Players (per D4) and admins | In progress |
| Closed | Online scoring disabled; admin reviews scores and adjusts teams | Admins only | Scoring in review |
| Finalized | Results computed; placements, winnings and handicaps recorded | Nobody (reopen first) | Final |
| Cancelled | Round not played or abandoned (e.g. rainout) | Nobody | Cancelled |

| Transition | Preconditions | Side effects |
|---|---|---|
| Scheduled → Active | Voting (if used) is closed; game, course, entry fee and groups are set | Create one score record per player (or per team) with NULL hole scores; set front/back nine per group (regular rounds) or the event's holes (special events) |
| Active → Closed | — | Disable online score entry |
| Closed → Finalized | Every score record is complete, or admin marks missing players as no-show (D19) | Run the game's scoring function; compute placement, ties, skins, payouts; store the course, handicap and payout values used (section 2.11); update handicaps if the game counts toward handicap; record who finalized and when |
| Finalized → Closed | Admin confirms | Mark this round's placements, payouts and handicap updates as voided (kept in history, not deleted); restore each player's handicap index to its value before this round (D18) |
| → Cancelled | Admin confirms | No payouts; any entry fees collected become credits toward each player's next round (D20) |

## 6. Data Model (proposed)
All tables also have `id`, `created_at` and `updated_at`. Money is stored in integer cents. Handicaps are stored to one decimal place. Reference tables (course, tee, game, payout_table, member, league) also have `archived_at`; they are archived, never deleted (section 2.11).

### Courses
- **course** — name, address, phone, website, notes. Any course can be a league's home course or a special event course.
- **course_photo** — course_id, file_path, caption, is_scorecard
- **tee** — course_id, name/color, sort_order (front to back), front9_course_rating, front9_slope, back9_course_rating, back9_slope
- **hole** — course_id, number (1–18), par, handicap_index (stroke index)
- **hole_yardage** — hole_id, tee_id, yards

### Members and leagues
- **member** — member_number (unique ID entered after the shared login, D25), first_name, last_name, email, phone, is_admin, admin_email (unique, admins only), admin_password_hash (admins only), default_tee_id, handicap_index, handicap_override (nullable), status (active/inactive)
- **league** — name, home_course_id, default_tee_time_set_id, start_date, end_date, default_entry_fee_cents, shared_login_id, shared_password_hash
- **league_membership** — league_id, member_id, flight (1/2/3), registered_at; unique (league_id, member_id)
- **password_reset** — member_id (admins only), token_hash, expires_at, used_at

### Games and payouts
- **game** — name, description, game_type (selects the scoring function), is_team_game, min_team_size, max_team_size, uses_flights, skins_enabled, skin_amount_cents, points_enabled (tick, tack, toe), points_amount_per_player_cents (D30), handicap_percent, counts_toward_handicap, payout_table_id
- **payout_table** — name (e.g. "Individual by place", "Team")
- **payout_table_row** — payout_table_id, place, percent

### Tee times, rounds, groups and teams
- **tee_time_set** — league_id, name (e.g. "Regular", "Fall Classic at Pine Hills"), course_id
- **tee_time** — tee_time_set_id, nine (front/back/all), base_time, sort_order
- **round** — league_id, course_id, tee_time_set_id, game_id (nullable until voting closes), is_special_event, event_name, holes_played (front/back/all — set for special events; regular rounds use the group's nine), play_date, entry_fee_cents, tee_time_offset_minutes, voting_opens_at, voting_closes_at, status (scheduled/active/closed/finalized/cancelled), finalized_at, finalized_by
- **round_game_option** — round_id, game_id (candidate games for voting)
- **game_vote** — round_id, member_id, game_id, voted_at; unique (round_id, member_id)
- **round_signup** — round_id, member_id, is_playing, paid (D6), credit_applied_id (nullable, member_credit used to pay); unique (round_id, member_id)
- **member_credit** — member_id, source_round_id (the cancelled round), amount_cents, applied_round_id (nullable until used) (D20)
- **round_group** — round_id, tee_time_id, nine (front/back), group_number
- **round_group_member** — round_group_id, member_id
- **team** — round_id, name, handicap_applied
- **team_member** — team_id, member_id

### Scoring and results
- **score** — round_id, member_id (nullable for team score), team_id (nullable), flight (copied at start of round), tee_id, course_rating_used, slope_used, par_used, handicap_index_used, handicap_percent_used, course_handicap, handicap_applied, gross_total, net_total, place, winnings_cents, status (in_progress/complete/no_show)
- **hole_score** — score_id, hole_id, par_used, hole_handicap_used, strokes (NULL until entered), tee_id (for red/white/blue games), tick, tack, toe (flags), entered_by_member_id, entered_at
- **round_payout_snapshot** — round_id, finalization_number, flight (nullable for team games), pot_cents, place, percent_used, amount_cents, voided_at (the payout table as it was applied at finalization)
- **points_result** — round_id, finalization_number, member_id, points, payout_per_point_cents, amount_cents, voided_at (tick, tack, toe)
- **scorecard_image** — score_id, file_path, uploaded_by, uploaded_at
- **emailed_score** — round_id, member_id (matched from the email), from_address, received_at, body_text, parsed_scores_json (nullable), image_path (nullable), status (pending/applied/rejected), reviewed_by, reviewed_at (D5)
- **skin_result** — round_id, finalization_number, flight, hole_id, winner_member_id (nullable), amount_cents, carried_over, voided_at
- **handicap_history** — member_id, round_id (nullable), score_differential, handicap_index_before, handicap_index_after, source (calculated/admin override/starting), changed_by, voided_at
- Reopening a round (D18) sets `voided_at` on that round's results and handicap history rows; finalizing again writes new rows with the next `finalization_number`. Placement and winnings on **score** always show the current (non-voided) finalization.

### Communication and audit
- **notification** — league_id, title, body, published_at, emailed_at, created_by
- **message** — from_member_id, to_member_id, subject, body, sent_at, read_at
- **audit_log** — member_id (who: the admin, or the member ID entered after shared login), session_id, ip_address, action, entity_type, entity_id, before_json, after_json, created_at. Every score entry and every admin change to scores, teams, handicaps, votes and round status is logged.

## 7. Handicap Calculation
- Use the World Handicap System (WHS) method for 9-hole scores. 9-hole scores are converted to an 18-hole score differential using the WHS expected-score method.
- Course handicap for a nine: `Handicap Index ÷ 2 × (Slope ÷ 113) + (Course Rating − Par)`, using the rating, slope and par for the nine and tee played.
- One handicap index per player. Front-nine and back-nine differences are handled automatically by the separate ratings for each nine, so no separate front/back handicaps are kept.
- Apply WHS adjustments, including net double bogey as the maximum hole score for handicap purposes.
- Handicaps are recalculated only when a round is finalized and only for games marked "counts toward handicap". Rounds where handicap calculation is not indicated never change a handicap. (D23)
- Rounding follows WHS. (D23)
- The handicap index is the average of the best 8 of the most recent 20 18-hole differentials, per WHS. With fewer than 20 differentials, the WHS adjustment table is used, and the minimum number of scores to establish a handicap follows WHS. (D22)
- A new member's starting handicap is entered by an admin and recorded in handicap history as "starting". (D21)
- Admin can enter or override a player's handicap at any time. Overrides are recorded in handicap history.
- Reopening a finalized round reverses that round's handicap updates (D18).
- This is a league-only handicap, not an official GHIN handicap.

## 8. Performance / Hardware Constraints
- Expected concurrent users: 100 maximum.
- Peak load is during and right after a round, when players are entering scores on their phones.

## 9. Security & Access Control
- **Member login (shared):** all members log in with one shared login ID and password for the league. After logging in, the member enters their unique member ID number, which selects whose data they see, whose score they enter, and whose vote they cast. The member ID can be changed during the session (e.g. to enter scores for others in the group).
  - Admin sets and changes the shared login ID and password (at least 8 characters, stored with PHP `password_hash()`). Proposed: change it each season.
  - There is no per-member password reset; a forgotten shared password is re-sent by an admin or posted where members can see it.
- **Known risk of the shared login:** anyone who has the shared password and knows (or guesses) another member's ID can view that member's data, enter their score or cast their vote. Mitigations:
  - Member IDs are not sequential and not easy to guess (D25).
  - Rate-limit member ID entry (e.g. lock the session after 5 wrong IDs).
  - Every score entry and vote records the member ID used, session and IP address in the audit log, and admins can review and correct scores while the round is Closed.
  - Member contact details (email, phone) are shown only to admins.
- **Admin login (individual):** each admin has their own email and password (at least 8 characters, `password_hash()`), separate from the shared login. Admin passwords can be reset through an emailed, single-use link that expires (proposed: 1 hour). Admin pages are never reachable through the shared login.
- Roles: guest, member (shared login + member ID), admin. Admin pages and actions check the admin role on every request.
- CSRF protection on all forms (CodeIgniter built-in). Rate limiting on login and password reset.
- SSL must be in place so browsers do not show an insecure-website warning.
- Uploads are validated by file type and size, and served through the application, not directly from the upload folder.
- Guests never see member contact information.

## 10. Data Backup & Retention
- Backups run daily to a remote drive or cloud drive.
- Back up both the SQLite database (using SQLite's online backup, not a plain file copy while in use) and the uploaded pictures folder.
- Test a restore at least once before the season starts.
- All league data is kept indefinitely for historical purposes (section 2.11). There is no purge feature in the application.

## 11. Decisions Log
Status is `Open`, `Decided <date>` or `Deferred`. For decided items the third column is the decision; for open items it is the proposed default.

| # | Question | Decision / proposed default | Status |
|---|---|---|---|
| D1 | Is registration open, or does an admin approve it? Is email verification required? | Open registration with email verification; admin can deactivate | Decided 10-17-26 |
| D2 | Flight balancing rule and when flights are recalculated | Sort by handicap; use the cutoffs, then move boundary players so each flight has ≥15 when there are ≥45 players; recalculate before each round starts, then freeze for that round | Decided 10-17-26 |
| D3 | How players sign up for a round; group size; manual vs. automatic group assignment | Players mark "playing" for each round before it starts; groups of 4; admin assigns, with an auto-fill option | Decided 10-17-26 |
| D4 | Who can enter/edit a score during an active round? | Player enters their own; anyone in the same group can enter for the group; admins can edit any | Decided 10-17-26 |
| D5 | Email score submission format | Email can contain score card picture or list of holes and score for each hole | Decided 10-17-2026  |
| D6 | Is entry fee payment tracked in the software? | Admin checkbox "paid" per player per round | Decided 10-17-26 |
| D7 | Money rounding | Round payouts down to whole dollars; leftover goes to the 1st place payout in that flight | Decided 10-17-26 |
| D8 | Formula for dividing the pot among flights | Proportional to number of players in each flight | Decided 10-17-26 |
| D9 | Flight with fewer than 15 players | Pay only the places that exist and rescale the percentages to 100% | Decided 10-17-26 |
| D10 | Tie handling for placement | Tied players split the combined percentages of the places they occupy | Decided 10-17-26 |
| D11 | Is the $45 skins amount fixed regardless of flight size? | Fixed $5 per hole, admin-editable on the game | Decided 10-17-26 |
| D12 | Handicap applied to the total vs. allocated hole by hole | Applied to the total for placement; hole allocation used only for net double bogey | Decided 10-17-26 |
| D13 | What a tick, tack or toe is worth and how it affects payout | Each tick, tack, toe is worth 1 point. Payout per point is calculated by game and paid out to point winners | Decided 10-17-26 |
| D14 | Scramble team size and handling of uneven teams | Teams of 2–4; team handicap percentage set on the game | Decided 10-17-26 |
| D15 | Modified RWB: does "bogey" mean bogey or worse? | Bogey or worse | Decided 10-17-2026 |
| D16 | Network/SSL approach | Cloudflare Tunnel | Decided 10-17-2026  |
| D17 | Transactional email provider | FastMail | Decided 10-17-26 |
| D18 | Can a finalized round be reopened? | Yes, admin only; reverses payouts and handicap updates from that round | Decided 10-17-26 |
| D19 | Missing scores at finalization | Admin marks the player as no-show; no-shows are excluded from placement and the pot | Decided 10-17-26 |
| D20 | Entry fees for a cancelled round | Might be not collected, if collected then applied to next round | Decided 10-17-26 |
| D21 | Starting handicap for a new player | Admin enters it at registration | Decided 10-17-26 |
| D22 | Scores required to establish a handicap, and how many recent scores are used | Per WHS: best 8 of the last 20 18-hole differentials | Decided 10-17-26 |
| D23 | Handicap rounding | Per WHS, but only calculated on rounds where handicap calculation is indicated | Decided 10-17-26 |
| D24 | How do we coordinate with and get tee times from the golf course? | Admin coordinates with golf course, admin enters them manually | Decided 10-17-2026  |
| D25 | Member ID format and how it's assigned | Random, non-sequential 5-digit number assigned at first registration and kept across seasons; admin can regenerate it | Decided 10-17-26 |
| D26 | Do special event rounds count toward handicap and use the normal flight payouts? | Same as any round: follows the game's settings | Decided 10-17-26 |
| D27 | Voting ties, and can the admin override the voting result? | Admin breaks ties and may override; the override is logged | Decided 10-17-26 |
| D28 | Can members see vote counts before voting closes? | Yes, live counts | Decided 10-17-26 |
| D29 | Who may vote on a round's game? | All active members of the league | Decided 10-17-26 |
| D30 | Tick, tack, toe: how much money is set aside for points, and are points paid within each flight or across the whole field? | A fixed amount per player (set on the game by admin) is taken from the pot into a points pool for the whole field; payout per point = points pool ÷ total points earned | Decided 10-17-2026  |

## 12. Glossary
- **League:** one season of play at one course.
- **Round:** one day of league play, with one game.
- **Special event:** a round played at a course other than the league's home course, with its own tee time set.
- **Tee time set:** a named list of tee times. The league has a default set; a round can use a different one.
- **Member ID:** a member's unique ID number, entered after the shared login to identify who is using the site.
- **Archive:** hide a record from current use while keeping it for history. League data is archived, never deleted.
- **Group:** players who tee off together at one tee time.
- **Team:** players whose scores are combined for a team game. Often the same as a group, but not always.
- **Flight:** a handicap band (1, 2 or 3) used to divide the pot in individual games.
- **Pot:** total entry fees collected for a round.
- **Skin:** money won by having the single lowest gross score on a hole within a flight.
- **Handicap index:** a player's portable handicap. **Course handicap:** the strokes a player gets for a specific nine and tee.
