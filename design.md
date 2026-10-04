# AG Golf League Management Software — Design Document

## 1. Overview
This golf league management software will manage a public 9 hole golf league. 

## 2. English description of what I want to be able to do and store and provide
 - As admin, I want to be able to create/update/remove a golf course. Name, address, phone, slope etc.
    - As admin, I want to be able to store golf course pictures, including a score card picture
    - As admin, under the golf course I want to be able to create/update holes 1 to 180 with yardages for each tee, handicap, etc.
 - As admin, I want to be able to create/update/remove golf games. For each game there will be a name, type, use for handicap flag, scoring rules
 - As admin, I want to be able to create a league, storing the name, start and end dates, and assign golf courses the members will play at
 - As admin, once the league is created, I want to be able to create/update a list of tee times for the front and back nine holes
 - As admin, I need the ability to easily adjust tee times by maybe applying an offset to each one accounting for daylight availablity
 - As admin, once the league is created, I want to be able create rounds. There will be a round for each day the league is played.  Rounds will define the game to be played
 - As admin, I want to be able to create/update/remove notifications which are shown on the league main webpage and are emailed to all members
 - As admin, I want to be able to create/update/remove members (players)
 - As a user, I want to be able to register for the league which either creates a member record or updates an existing member record
 - As admin, I want to be able to created/update/remove groups. Groups will line up with tee times and will define what members play together and what times they play
 - As admin, for each round I need to be able to change the groups to play the opposite nine holes.  Groups will play front nine one week, back nine the next
 - As admin, I need to be able to close a round which disables online scoring
    - I need to be able to view/edit scores
    - I need to be able to finalize the round
        - Calculate scores based on game scoring rules
        - Applies any handicaps as defined by game scoring rules
        - Figure out placement in each flight.  IE 1, 2, 3 etc.
        - Figure out any skins if skins are active
    - Scoring records are updated with placement and winnings
 - As admin, I need to be able to start a round so it is active. Starting a round will create scoring records for all of the members.  Scores are set to 0.
 - As a user, I want to be able to go to the league home web page and see information about the league including, notifications and a list of rounds and the status of each round
 - As a user, I want to be able to go to a detail web page for a round and view detail information on the round, scoring if it has been played, placements and winnings
 - As a user, for the active round, I want to be able to enter my score online
 - As a user, I want to be able to submit a score card picture if I do not use online scoring
 - As a user, I want to be able to submit my round scores via email
 - As a user, I want to be able to go to my own member web page where I can view a list of the rounds I played in and my placement, winnings for the round, as well as my total winnings
 - As a user, I want to be able to send other users messages and I want to be able to read messages sent to me
 - As a guest, a non member, I want to be able to view a main league web page

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
