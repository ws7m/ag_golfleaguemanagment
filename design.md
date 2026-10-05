# AG Golf League Management Software — Design Document

## 1. Overview
This golf league management software will manage a public 9 hole golf league. 

## 2. English description of what I want to be able to do and store and provide
 - As admin, I want to be able to create/update/remove a golf course. Name, address, phone, slope etc.
    - As admin, I want to be able to store golf course pictures, including a score card picture
    - As admin, under the golf course I want to be able to create/update holes 1 to 18 with yardages for each tee, handicap, etc.
 - As admin, I want to be able to create/update/remove golf games. For each game there will be a name, type, use for handicap flag, scoring rules
 - As admin, I want to be able to create a league, storing the name, start and end dates, and assign golf courses the members will play at
    - For now a league will play at only one course
    - Existing members can be converted to the new league by re-registering
 - As admin, once the league is created, I want to be able to create/update a list of tee times for the front and back nine holes
 - As admin, I need the ability to easily adjust tee times by maybe applying an offset to each one accounting for daylight availablity
 - As admin, once the league is created, I want to be able create rounds. There will be a round for each day the league is played.  Rounds will define the game to be played, and entry fee per player
 - As admin, I want to be able to create/update/remove notifications which are shown on the league main webpage and are emailed to all members
 - As admin, I want to be able to create/update/remove members (players), I can ceate multiple admins as well.  Admins are just players with extra abilities to manage the league, members are assigned a flight, (1, 2, or 3), based on their handicap. Low handicap players (5 and lower) are flight 1. Mid handicap players 5.1 to 10 are flight 2. All players with handicap above 10 are flight 3. Flights will always try to be adjusted so there are at least 15 players in a flight.
 - As a user, I want to be able to register for the league which either creates a member record or updates an existing member record
 - As admin, I want to be able to created/update/remove groups. Groups will line up with tee times and will define what members play together and what times they play, groups do not always define teams
 - As admin, for each round I need to be able to change the groups to play the opposite nine holes.  Groups will play front nine one week, back nine the next
 - As admin, for team games, I need to be able to adjust members of a team after the round is complete and before scoring computation begins.
 - As admin, I need to be able to close a round which disables online scoring
    - I need to be able to view/edit scores
    - I need to be able to finalize the round
        - Calculate scores based on game scoring rules
        - Applies any handicaps as defined by game scoring rules
        - Figure out placement.  IE 1, 2, 3 etc.
        - Figure out any skins if skins are active
    - Scoring records are updated with placement and winnings
 - As admin, I need to be able to start a round so it is active. Starting a round will create scoring records for all of the members.  Scores are set to 0.
 - As a user, I want to be able to go to the league home web page and see information about the league including, notifications and a list of rounds and the status of each round
 - As a user, I want to be able to go to a detail web page for a round and view detail information on the round, scoring if it has been played, placements and winnings
 - As a user, for the active round, I want to be able to enter my score online, scoring is always a score for every hole played, hole-by-hole scoring.
 - As a user, I want to be able to submit a score card picture if I do not use online scoring
 - As a user, I want to be able to submit my round scores via email
 - As a user, I want to be able to go to my own member web page where I can view a list of the rounds I played in and my placement, winnings for the round, as well as my total winnings
 - As a user, I want to be able to send other users messages and I want to be able to read messages sent to me
 - As a guest, a non member, I want to be able to view a main league web page

### 2.1 Game defintions
 - All players pay in $10 into the pot to play into any game/round
 - Some game types specify that a percentage will be applied to a players handicap.  The game will specify the percentage value.
 - Some team game types specify that the handicaps will be averaged and a percentable of that resulting average will be appled as the handicap for the team.  The game will specify the percentage
 - For all individual stroke scoring, winnings are divided by flights.  
    - Table in software will define the percentage of the pot that each flight gets to payout from. This will also be based on number of players in each flight.
    - Payouts are made based on places 1 to 15 based on the following percentages, The above flight percents and this table percents below will be editable in the software by admin.
    1	11%
    2	10.5%
    3	10.0%
    4	9.5%
    5	9.0%
    6	8.5%
    7	8.0%
    8	7.5%
    9	7.0%
    10	6.5%
    11	6.0%
    12	3.0%
    13	2.0%
    14	1.0%
    15	0.5%
- For individual stroke play with skins the pot is again divided by flights as above, however $5 per hole for skins ($45 total) is pulled from the flight pot to be paid out in skins winnings
    Payout is like stroke pay above with the possible addition of skins payout if the player wins holes.
    The software computes in the flight who wins, loses or ties a hole based on gross score for the hole and applies the skin amount appropriately
    If the hole is tied, the skin carries over to the next hole adding the skin amount to be won to the next hole. The skin amount can carry over multiple holes so it is possible it could be carried over through the full nine holes making the 9th hole skin worth $45.
    If at the end of nine holes the skin has not been won it returns to the pot.
- For team play like scrambles there are no flights. Payout uses the entire pot and pays out to teams based on a percentage table like the 1-15 table above. Winnings are divided amongst the team.
 - Games to be supported:
    - Stroke play
        - Description: Standard stroke play
        - Scoring: Individual scoring shots per hole
        - Handicap: Player handicap is applied at completion
        - Score will be used to compute player handicap
        - Results: Winning position is calculated based on net score. Number of winners will depend upon number of players playing, but typically will be 15.
        - Payout is handled as described above, by flight, by score position within the flight
    - Stroke play with Skins
        - Description: Standard stroke play, skins play on each hole
        - Scoring: Individual scoring shots per hole
        - Handicap: Player handicap is applied at completion
        - Score will be used to compute player handicap
        - Payout will be handled like stroke play with the addition of possible skins winning payout
    - Scramble
         - Description: Team based, each player hits, players move to best ball, all hit again, continue until holed out.
         - Team formation: By definition the team is the members of the group you teed off with.  Score page will have a group selection to indicate the group. All players select the same group.
         - Scoring: Each player enters the same shot count per hole for their score
         - Handicap: Team members handicaps are averaged and a percentage of that final average is applied to the team score at completion
         - Score will not be used to compute players handicaps
         - Payout is handled like stroke play except the the strokes are based on the team score minus the team handicap
    - Modified best ball
         - Description: Standard stroke play except that each player, once per hole, gets to move their ball to the best ball, remainder is played individually until holed out
         - Scoring: Individual scoring shots per hole
         - Handicap: A percentage of players handicap is applied at completion
         - Score will not be used to complete player handicap
         - Payout will be handled like stroke play
    - Red, white, blue
         - Descripion: Players choose to hit from red, white or blue tees on holes of their choice.  Must hit from 3 reds, 3 whites, and 3 blues
         - Scoring: Individual scoring shots per hole
         - Handicap: A percentage of player handicap is applied at completion
         - Score will not be used to compute players handicap
         - Payout will be handled like stroke pay
    - Modified red, white, blue
         - Description: Players start on their normal tee color.  A bogey, player moves forward a tee, a par player remains on current color tee, a birdie player moves back a tee
         - Scoring: Individual scoring shots per hole
         - Handicap: A percentage of player handicap is applied at completion
         - Score will not be used to compute players handicap
         - Payout will be handled like stroke play
    - Irons only
         - Discrption: Entire round is played with irons only
         - Scoring: Individual scoring shots per hole
         - Handicap: Player handicap is applied at completion
         - Score will not be used to compute players handicap
         - Payout will be handled like stroke play
    - 3 clubs only
         - Description: Player must choose three clubs and use only those three clubs for the entire round
         - Scoring: Individual scoring shots per hole
         - Handicap: Player handicap is applied at commpletion
         - Score will not be used to compute players handicap
         - Payout will be handled like stroke play
    - Tick, tack, toe
         - Description: Standard stroke play except players keep trak of: First on green (tick), Closet to the pin (tack), First in the hole (toe)
         - Scoring: Invidual scoring shots per hole, players indicate if they got a tick, tack, or toe on scoring page or score card
         - Handicap: Player handicap is applied at completion
         - Score will be used to compute player handicap
         - Payout will be handled like stroke play 
 - Games will define the placement winning percentages of money based on scoring and game scoring rules
 - Because scoring rules are complex, a game will basically cause code during round finalization to invoke a scoring function that will process each score, apply handicaps and calculate placement

## 3. Technology & Hosting
- **Language/Framework:** PHP / CodeIgniter
- **Database:** SQLite (confirm or revise)
- **Hosting Environment:** Self-hosted on home Linux PC
- **Constraints:** _Hardware specs, network setup (port forwarding, dynamic DNS, reverse proxy?), domain/SSL plans, backup strategy_

## 4. Information/model hierarchy
- Data models will be defined based on the above list of needs

## 5. Handicap Calculation
- _Requirements:_
- Use World Handicap System (WHS) method for calculating 9 hole handicap for each player
- Follow WHS guide likes for double bogey limits and any other guidelines
- Admin can enter/override a users handicap
- Admin triggers any handicap updates when closing a round
- _Open questions:_
    Should there be different handicaps maintained for front nine versus back nine?

## 6 Performance / Hardware Constraints
- _Expected concurrent users: 100 concurrent users maximum

## 7 Security & Access Control
- _Authentication method, session handling, admin vs. player access control_
- Simple username/password login for both users and admin, require minimum 8 character passwords
- Users can reset passwords using common link that sends email to confirm and allow password change
- Admin can reset and enter new password for users
- SSL must be in place so browsers do not complain about insecure website

## 8 Data Backup & Retention
- _Backup frequency/method, how long is historical data kept_
- Backup frequency will be daily - Backup will be made to a remote drive or cloud drive
- Historical data is kept until Admin purges it

## 9. Open Questions / Decisions Log
- How do I coordinate and get tee times from the golf course?
