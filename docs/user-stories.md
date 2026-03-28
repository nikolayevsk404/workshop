# TROCA — User Stories

## Overview

This document contains user stories for TROCA, a web-based digital adaptation of a Brazilian tabletop token-trading game where the player competes against an AI opponent in strategic resource management.

**User Types:**
- **Visitor** — Unauthenticated user on the landing/login page
- **Player** — Authenticated user who plays matches against the AI
- **AI Opponent** — Server-side automated opponent (stub implementation; AI logic will be implemented manually later)

**Scope Note:** This document covers authentication, match setup, full player-side game mechanics, scoring, and leaderboard. The AI opponent turn logic is represented as a stub/hook — all turn infrastructure exists, but the AI decision-making strategy will be implemented separately.

---

## 1. Authentication & Registration

### US-1.1: Player Registration
**As a** Visitor
**I want to** register with a username, email, and password
**So that** I can create an account and play matches

**Acceptance Criteria:**
- [ ] Registration form collects: username, email, password, password confirmation
- [ ] Username must be unique and between 3–20 characters
- [ ] Email must be unique and valid
- [ ] Password must meet minimum security requirements (8+ characters)
- [ ] Email verification is required before accessing the arena
- [ ] After verification, user is redirected to the arena dashboard
- [ ] Registration page matches the neon noir design (dark theme, glowing accents)

**Expected Result:** Player account is created, verified, and ready to play.

---

### US-1.2: Player Login
**As a** Visitor
**I want to** log in with my email and password
**So that** I can access my account and play matches

**Acceptance Criteria:**
- [ ] Login form collects: email, password
- [ ] "Remember me" option available
- [ ] Invalid credentials show a clear error message
- [ ] Successful login redirects to the arena dashboard
- [ ] Login page matches the neon noir design

**Expected Result:** Player is authenticated and enters the arena.

---

### US-1.3: Password Reset
**As a** Player
**I want to** reset my password
**So that** I can regain access to my account if I forget it

**Acceptance Criteria:**
- [ ] "Forgot password" link available on the login page
- [ ] Reset email sent with a secure, time-limited link (60 minutes)
- [ ] User can define a new password meeting security requirements
- [ ] After reset, user is redirected to the login page with a success message

**Expected Result:** Player regains account access securely.

---

### US-1.4: Logout
**As a** Player
**I want to** log out of my account
**So that** I can end my session securely

**Acceptance Criteria:**
- [ ] Logout option visible in the sidebar navigation
- [ ] Session is destroyed on logout
- [ ] User is redirected to the login page

**Expected Result:** Player session ends and they return to the login screen.

---

## 2. Arena Dashboard

### US-2.1: View Arena Dashboard
**As a** Player
**I want to** see the main arena dashboard after logging in
**So that** I can start a new match or navigate to other sections

**Acceptance Criteria:**
- [ ] Dashboard displays:
    - Player username and rank
    - "Start Match" / "New Game" call-to-action
    - Navigation sidebar with: Arena, Leaderboard, Settings, Logout
- [ ] Dashboard follows the neon noir design with dark background and glowing UI elements
- [ ] If the player has an ongoing match, show an option to resume it

**Expected Result:** Player has a clear entry point to start playing or navigate the app.

---

## 3. Match Setup

### US-3.1: Select Quotation Cards
**As a** Player
**I want to** choose 2 quotation cards from the 10 available before starting a match
**So that** I can define the token exchange rates that will be active during the game

**Acceptance Criteria:**
- [ ] All 10 quotation cards are displayed with a visual representation of their exchange rates
- [ ] Each quotation card clearly shows which tokens can be traded for which (e.g., 1 red = 2 white)
- [ ] Player must select exactly 2 cards — no more, no fewer
- [ ] Selected cards are visually highlighted (active state)
- [ ] A counter shows "X/2 selected"
- [ ] Player cannot proceed to start the match until exactly 2 cards are selected
- [ ] The same 2 quotation cards will apply to both the player and the AI opponent

**Expected Result:** Player has chosen 2 quotation cards that define trading rules for the match.

---

### US-3.2: Select Difficulty Tier
**As a** Player
**I want to** choose a difficulty tier before starting a match
**So that** I can challenge myself at different levels and earn proportional rewards

**Acceptance Criteria:**
- [ ] Three difficulty tiers are displayed:
    - Tier 1 (e.g., "Padrão Primário") — easiest, lowest XP reward
    - Tier 2 (e.g., "Cadeia Cruzada") — medium difficulty, medium XP reward
    - Tier 3 (e.g., "Mestre do Caos") — hardest, highest XP reward
- [ ] Each tier shows its name, visual indicator (star rating), and XP reward
- [ ] Player must select exactly 1 tier
- [ ] Selected tier is visually highlighted
- [ ] The difficulty tier is stored with the match and will influence the AI behavior (to be implemented later)

**Expected Result:** Player has chosen a difficulty tier for the upcoming match.

---

### US-3.3: Start a New Match
**As a** Player
**I want to** start a new match after configuring quotation cards and difficulty
**So that** I can begin playing against the AI

**Acceptance Criteria:**
- [ ] "Start Match" button is enabled only when 2 quotation cards and 1 difficulty tier are selected
- [ ] On start, the system:
    - Creates a new match record in the database
    - Shuffles the 45 cards and distributes them into 4 compartments (groups of 5 per compartment, with remaining cards unused)
    - Only the top card of each compartment is face-up
    - Sets the selected quotation cards as active for the match
    - Stores the selected difficulty tier
    - Randomly determines who goes first (player or AI)
    - Initializes both player and AI with 0 tokens
- [ ] Player is redirected to the game board screen

**Expected Result:** A new match is initialized and the game board is ready.

---

## 4. Game Board & Turn System

### US-4.1: View Game Board
**As a** Player
**I want to** see the full game board during a match
**So that** I can understand the current game state and make decisions

**Acceptance Criteria:**
- [ ] The game board displays:
    - **Player's token inventory** — count of each color (red, green, white, yellow, blue) with colored indicators
    - **Match summary** — total actions, dice rolls, trades completed
    - **Recent history log** — last actions taken (e.g., "Rolled dice", "Trade completed", "Start of turn")
    - **Current objective card** — the card the player is targeting, showing required token colors and progress
    - **Active quotation cards** — the 2 selected quotation cards with their exchange rates, each with an "Execute Trade" button
    - **Card compartments** — 4 compartments showing the top face-up card of each, with the required 5 colored tokens visible
    - **Dice roll button** — prominent action button to roll the dice
- [ ] The board clearly indicates whose turn it is (player or AI)
- [ ] UI follows the neon noir design with dark theme, glowing borders, and colored token indicators

**Expected Result:** Player has full visibility of the game state to make informed decisions.

---

### US-4.2: Roll the Dice
**As a** Player
**I want to** roll the color dice on my turn
**So that** I can gain a new token

**Acceptance Criteria:**
- [ ] "Roll Dice" button is only active during the player's turn
- [ ] Rolling the dice is disabled if the player has already performed an action this turn (roll or trade)
- [ ] The dice has 6 faces: red, green, white, yellow, blue, and "free" (wild)
- [ ] On roll:
    - A dice animation plays showing the result
    - If a color is rolled, 1 token of that color is added to the player's inventory
    - If "free" is rolled, a color selection modal appears for the player to choose any color
- [ ] The player's token inventory updates immediately
- [ ] The action is logged in the recent history
- [ ] After rolling, the player may optionally purchase a card (see US-4.5) before ending the turn

**Expected Result:** Player receives 1 token of the rolled/chosen color.

---

### US-4.3: Trade Tokens
**As a** Player
**I want to** trade tokens using one of the active quotation cards
**So that** I can exchange tokens strategically to work toward purchasing a card

**Acceptance Criteria:**
- [ ] Trade option is only available during the player's turn
- [ ] Trading is disabled if the player has already performed an action this turn (roll or trade)
- [ ] Player selects one of the 2 active quotation cards
- [ ] The quotation card shows all available exchange options (bidirectional trades)
- [ ] Player selects which trade to execute from the card's options
- [ ] The system validates that the player has the required tokens to give
- [ ] On valid trade:
    - Tokens given are removed from the player's inventory
    - Tokens received are added to the player's inventory
    - The trade is logged in the recent history
- [ ] On invalid trade (insufficient tokens):
    - An error message is displayed
    - No tokens are exchanged
- [ ] Only 1 trade is allowed per turn
- [ ] After trading, the player may optionally purchase a card (see US-4.5) before ending the turn

**Expected Result:** Player exchanges tokens according to the quotation card rates.

---

### US-4.4: Free Dice — Choose Token Color
**As a** Player
**I want to** choose any token color when I roll "free" on the dice
**So that** I can pick the most strategically valuable color

**Acceptance Criteria:**
- [ ] When the dice lands on "free", a color selection modal/overlay appears
- [ ] All 5 token colors are shown as selectable options (red, green, white, yellow, blue)
- [ ] Player must select exactly 1 color
- [ ] After selection, 1 token of the chosen color is added to the player's inventory
- [ ] The choice is logged in the recent history as "Rolled free — chose [color]"

**Expected Result:** Player receives 1 token of their chosen color.

---

### US-4.5: Purchase a Card
**As a** Player
**I want to** purchase a face-up card from a compartment by spending 5 matching tokens
**So that** I can earn points

**Acceptance Criteria:**
- [ ] Card purchase is only available after the player has performed their turn action (roll or trade)
- [ ] A purchasable card must be face-up (top of its compartment)
- [ ] The card displays the 5 required token colors
- [ ] The system validates the player has all 5 required tokens
- [ ] If eligible, a "Buy Card" button is shown on the card
- [ ] On purchase:
    - The 5 matching tokens are removed from the player's inventory
    - Points are calculated based on the number of remaining tokens and the card's star rating (see scoring table)
    - The scored points are recorded for this match
    - The card is removed from the compartment
    - The next card in the compartment becomes face-up (if any remain)
    - The purchase is logged in the recent history
- [ ] Purchasing a card is **optional** — the player may choose to wait for a better scoring opportunity
- [ ] The player is NOT obligated to buy even if they have the required tokens

**Expected Result:** Player acquires the card, scores points, and returns tokens to the board supply.

---

### US-4.6: Token Limit Enforcement (Maximum 10)
**As the** System
**I want to** enforce the 10-token limit per player
**So that** the game rules are respected

**Acceptance Criteria:**
- [ ] After any action (roll, trade), the system checks if the player holds more than 10 tokens
- [ ] If the player exceeds 10 tokens:
    - A warning is displayed indicating the excess
    - The player must reduce their token count before the next turn
    - The player can reduce by purchasing a card or returning excess tokens
- [ ] When returning excess tokens, the player chooses which colors to return
- [ ] The turn cannot end with more than 10 tokens in hand

**Expected Result:** No player ever holds more than 10 tokens at the end of their turn.

---

### US-4.7: End Turn
**As a** Player
**I want to** end my turn after performing my action and optional card purchase
**So that** the AI opponent can take its turn

**Acceptance Criteria:**
- [ ] "End Turn" button is available after the player has completed their action (roll or trade)
- [ ] End Turn is blocked if the player holds more than 10 tokens (see US-4.6)
- [ ] On ending the turn:
    - The turn passes to the AI opponent
    - The AI turn stub is triggered (placeholder for future AI logic)
    - After the AI stub completes, the turn returns to the player
- [ ] Turn counter increments

**Expected Result:** Turn transitions to the AI opponent.

---

### US-4.8: AI Opponent Turn (Stub)
**As the** System
**I want to** execute the AI opponent's turn as a placeholder
**So that** the turn infrastructure is ready for future AI logic implementation

**Acceptance Criteria:**
- [ ] When the AI's turn begins, the game board indicates "AI is thinking..." or similar visual feedback
- [ ] The AI turn calls a dedicated stub method/service that can be extended later
- [ ] The stub method receives the full game state: AI tokens, available cards, active quotation cards, difficulty tier
- [ ] For now, the stub performs a simple default action (e.g., always rolls the dice with a random result)
- [ ] The AI's action is logged in the recent history (e.g., "AI rolled dice — received 1 blue token")
- [ ] After the AI turn completes, the turn returns to the player
- [ ] The AI respects the same game rules: 1 action per turn, 10-token limit, optional card purchase

**Expected Result:** AI turn executes with a basic placeholder action, maintaining the full turn cycle.

---

### US-4.9: Compartment Star Bonus
**As the** System
**I want to** activate a star bonus when all cards in a compartment are sold
**So that** future card purchases score higher as the game progresses

**Acceptance Criteria:**
- [ ] When the last card from a compartment is purchased, a star bonus is activated
- [ ] A visual indicator appears on the board showing the star bonus is active
- [ ] From that moment onward:
    - All normal cards (no star) are scored as if they had 1 star
    - All cards with 1 printed star are scored as if they had 2 stars
- [ ] The bonus is cumulative if multiple compartments are emptied (each empty compartment adds a star level)
- [ ] The scoring table adjusts dynamically based on active star bonuses

**Expected Result:** Scoring escalates as compartments are emptied, adding strategic depth.

---

## 5. Scoring & Match End

### US-5.1: Score Calculation on Card Purchase
**As the** System
**I want to** calculate and record points when a card is purchased
**So that** the player's score reflects their efficiency

**Acceptance Criteria:**
- [ ] Points are calculated immediately upon card purchase
- [ ] The scoring formula considers:
    - Number of tokens remaining after the purchase
    - Card star rating (normal, 1 star, 2 stars)
    - Active compartment star bonuses (see US-4.9)
- [ ] Scoring table:

| Remaining Tokens | Normal | 1 Star | 2 Stars |
|---|---:|---:|---:|
| 3 or more | 1 | 2 | 3 |
| 2 | 2 | 3 | 5 |
| 1 | 3 | 5 | 8 |
| 0 | 5 | 8 | 12 |

- [ ] Points are added to the player's running match total
- [ ] The updated score is visible on the game board

**Expected Result:** Points accurately reflect the player's token management efficiency.

---

### US-5.2: Game End Condition
**As the** System
**I want to** end the match when cards from 2 compartments are fully sold
**So that** the game concludes according to the rules

**Acceptance Criteria:**
- [ ] The system tracks the number of cards remaining in each compartment
- [ ] When the 2nd compartment becomes empty (all its cards purchased), the game ends immediately
- [ ] No further turns are taken after the end condition is met
- [ ] The game transitions to the results screen

**Expected Result:** The match ends at the correct moment per game rules.

---

### US-5.3: View Match Results
**As a** Player
**I want to** see the final match results after the game ends
**So that** I can understand how I performed compared to the AI

**Acceptance Criteria:**
- [ ] Results screen displays:
    - Player's final score with breakdown (points per card purchased)
    - AI's final score with breakdown
    - Winner announcement (or draw)
    - Number of cards purchased by each side
    - Difficulty tier played
    - XP earned from the match
- [ ] Tiebreaker rules applied:
    1. Fewer cards purchased wins
    2. If still tied, the match is a draw
- [ ] "Play Again" button to return to match setup
- [ ] "Back to Arena" button to return to the dashboard
- [ ] Match result is saved to the database for leaderboard

**Expected Result:** Player sees a clear summary of the match outcome.

---

## 6. Leaderboard

### US-6.1: View Leaderboard
**As a** Player
**I want to** see a leaderboard ranking all players
**So that** I can compare my performance against others

**Acceptance Criteria:**
- [ ] Leaderboard displays a ranked list of players sorted by total XP (or total wins)
- [ ] Each entry shows:
    - Rank position
    - Player username
    - Total matches played
    - Total wins
    - Total XP
- [ ] The current player's row is visually highlighted
- [ ] Leaderboard is accessible from the sidebar navigation
- [ ] Pagination or infinite scroll for large player counts

**Expected Result:** Player can see their global ranking and compare with others.

---

### US-6.2: XP Progression
**As a** Player
**I want to** earn XP after completing matches
**So that** I feel a sense of progression and achievement

**Acceptance Criteria:**
- [ ] XP is awarded at the end of each match based on:
    - Difficulty tier selected (higher tier = more base XP)
    - Match outcome (win grants bonus XP)
- [ ] XP is cumulative across all matches
- [ ] Player's total XP and rank are visible on the dashboard and leaderboard
- [ ] Player rank is derived from XP thresholds (e.g., "Diamond", as shown in the design)

**Expected Result:** Players are rewarded for playing and winning, driving engagement.

---

## 7. Settings

### US-7.1: Update Player Settings
**As a** Player
**I want to** update my account settings
**So that** I can manage my profile and preferences

**Acceptance Criteria:**
- [ ] Settings page accessible from the sidebar
- [ ] Player can update:
    - Username
    - Email (with re-verification)
    - Password (requires current password confirmation)
- [ ] Changes are saved and confirmed with a success message

**Expected Result:** Player can manage their account details.

---

## Appendix: User Story Status

| ID | Story | Priority | Status |
|----|-------|----------|--------|
| US-1.1 | Player Registration | High | Pending |
| US-1.2 | Player Login | High | Pending |
| US-1.3 | Password Reset | Medium | Pending |
| US-1.4 | Logout | High | Pending |
| US-2.1 | View Arena Dashboard | High | Pending |
| US-3.1 | Select Quotation Cards | High | Pending |
| US-3.2 | Select Difficulty Tier | High | Pending |
| US-3.3 | Start a New Match | High | Pending |
| US-4.1 | View Game Board | High | Pending |
| US-4.2 | Roll the Dice | High | Pending |
| US-4.3 | Trade Tokens | High | Pending |
| US-4.4 | Free Dice — Choose Token Color | High | Pending |
| US-4.5 | Purchase a Card | High | Pending |
| US-4.6 | Token Limit Enforcement | High | Pending |
| US-4.7 | End Turn | High | Pending |
| US-4.8 | AI Opponent Turn (Stub) | Medium | Pending |
| US-4.9 | Compartment Star Bonus | Medium | Pending |
| US-5.1 | Score Calculation | High | Pending |
| US-5.2 | Game End Condition | High | Pending |
| US-5.3 | View Match Results | High | Pending |
| US-6.1 | View Leaderboard | Medium | Pending |
| US-6.2 | XP Progression | Medium | Pending |
| US-7.1 | Update Player Settings | Low | Pending |
