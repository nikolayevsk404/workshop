# TROCA — Project Phases & Task List

## Overview

This document breaks down the TROCA project into implementation phases with granular tasks. Each task specifies the automated feature tests required as acceptance criteria. Phases are numbered for reference when delegating to an AI agent (e.g., "Implement Phase 3.2").

**Status Legend:** `[ ]` = Pending | `[x]` = Completed

---

## Phase 1: Frontend Foundation (No Tests Required)

> Design system setup, reusable UI components, and base layouts following the neon noir visual identity.
> Reference: `docs/design/*` screens for colors, typography, and component styles.

### Phase 1.1: Tailwind Theme Configuration

- [ ] Configure custom color palette in `resources/css/app.css` for neon noir theme:
    - Background colors (deep dark blues/blacks)
    - Primary accent (neon blue/purple glow)
    - Success/green accent
    - Danger/red accent
    - Warning/yellow accent
    - Text colors (light grays, whites)
    - Border/glow colors for card outlines
    - Token-specific colors: red (`#EF4444`), green (`#22C55E`), white (`#F8FAFC`), yellow (`#EAB308`), blue (`#3B82F6`)
- [ ] Configure custom font (from design screens)
- [ ] Set up dark theme as the default (no light mode toggle needed)

### Phase 1.2: Base UI Components (Blade Components)

- [ ] **Button component** (`<x-button>`) — primary, secondary, danger, ghost variants; sizes (sm, md, lg); disabled state; loading state with spinner; glow effect on hover
- [ ] **Text input component** (`<x-input>`) — with label, placeholder, error state, icon slot (left); dark-themed with subtle border glow
- [ ] **Select component** (`<x-select>`) — with label, options, placeholder, error state; dark-themed
- [ ] **Checkbox component** (`<x-checkbox>`) — with label, checked state; neon accent color
- [ ] **Radio button component** (`<x-radio>`) — with label, selected state; neon accent color
- [ ] **Modal component** (`<x-modal>`) — overlay backdrop, centered content, close button, title slot, body slot, footer slot; animation (fade/slide); Alpine.js for open/close toggle
- [ ] **Card component** (`<x-card>`) — dark background, subtle border glow, header slot, body slot; hover effect
- [ ] **Badge component** (`<x-badge>`) — for ranks, statuses; color variants
- [ ] **Alert/Flash message component** (`<x-alert>`) — success, error, warning, info variants; dismissible with Alpine.js

### Phase 1.3: Guest Layout (Unauthenticated)

- [ ] Create `resources/views/layouts/guest.blade.php`
    - Centered content card on dark background
    - TROCA logo/title at top
    - Minimal layout matching login/register design screens
    - Flash message area
    - Livewire and Vite asset directives

### Phase 1.4: Authenticated Layout (App Shell)

- [ ] Create/update `resources/views/layouts/app.blade.php`
    - Left sidebar navigation with:
        - Player avatar/username area at top
        - Arena link (active state)
        - Leaderboard link
        - Settings link
        - "Roll Dice" CTA button at bottom of sidebar
        - Support link
        - Logout link
    - Top bar with notifications icon and player username/rank
    - Main content area
    - Flash message area
    - Neon noir styling matching `docs/design/troca_jogo/screen.png`
- [ ] Create sidebar navigation Blade component (`<x-sidebar>`)
- [ ] Create top bar Blade component (`<x-topbar>`)

### Phase 1.5: Token Color Dot Component

- [ ] Create `<x-token-dot>` component — renders a colored circle for token display
    - Props: color (red/green/white/yellow/blue), size (sm, md, lg)
    - Uses the token hex colors from the theme
    - Reused across the entire game UI (cards, quotation cards, inventory, etc.)

---

## Phase 2: Database, Models & Seeders

> Implements all migrations, Eloquent models, factories, and seeders from `docs/database-schema.md`.

### Phase 2.1: Lookup Table Migrations & Seeders

- [ ] Create migration and seeder for `token_colors` (5 colors with hex codes)
- [ ] Create migration and seeder for `difficulty_tiers` (3 tiers with XP rewards)
- [ ] Create migration and seeder for `match_statuses` (pending, in_progress, completed, abandoned)
- [ ] Create migration and seeder for `match_result_types` (player_win, ai_win, draw)
- [ ] Create migration and seeder for `turn_action_types` (roll_dice, trade, purchase_card, return_tokens)
- [ ] Create migration and seeder for `participant_types` (player, ai)
- [ ] Create migration and seeder for `trade_sides` (left, right)
- [ ] Create migration and seeder for `player_ranks` (Bronze through Diamond with min_xp thresholds)
- [ ] Create migration and seeder for `scoring_rules` (12 rows: 4 token brackets × 3 star levels)

**Tests (`tests/Feature/Database/LookupTableSeederTest.php`):**
- [ ] Test that all lookup tables are seeded with the correct number of rows
- [ ] Test that `token_colors` has exactly 5 entries with valid hex codes
- [ ] Test that `difficulty_tiers` has 3 entries ordered by `sort_order`
- [ ] Test that `scoring_rules` has 12 entries covering all bracket/star combinations
- [ ] Test that `player_ranks` has entries with increasing `min_xp` values

### Phase 2.2: Lookup Table Models

- [ ] Create `TokenColor` model with `$fillable`, relationships
- [ ] Create `DifficultyTier` model with `$fillable`, relationships
- [ ] Create `MatchStatus` model with `$fillable`, slug-based helper scopes
- [ ] Create `MatchResultType` model with `$fillable`
- [ ] Create `TurnActionType` model with `$fillable`
- [ ] Create `ParticipantType` model with `$fillable`
- [ ] Create `TradeSide` model with `$fillable`
- [ ] Create `PlayerRank` model with `$fillable`, scope for rank by XP
- [ ] Create `ScoringRule` model with `$fillable`, method to calculate points

**Tests (`tests/Feature/Models/LookupModelTest.php`):**
- [ ] Test `PlayerRank::forXp($xp)` returns the correct rank for given XP thresholds
- [ ] Test `ScoringRule::calculatePoints($remainingTokens, $starCount)` returns correct points for all 12 combinations

### Phase 2.3: User Model Updates & Migration

- [ ] Create migration to add `username` (unique, varchar 20), `total_xp` (default 0), and `player_rank_id` (nullable FK) columns to `users` table
- [ ] Remove `name` column from `users` table (replace with `username`)
- [ ] Update `User` model: update `$fillable`, add `playerRank()` belongsTo relationship, add `matches()` hasMany relationship
- [ ] Update `UserFactory` to generate `username` instead of `name`
- [ ] Update `DatabaseSeeder` test user to use `username`

**Tests (`tests/Feature/Models/UserModelTest.php`):**
- [ ] Test user can be created with `username`, `email`, `password`
- [ ] Test `username` uniqueness constraint
- [ ] Test `username` length validation (3–20 chars)
- [ ] Test user `playerRank` relationship returns correct rank
- [ ] Test user `matches` relationship returns associated matches

### Phase 2.4: Game Component Migrations & Models

- [ ] Create migration for `quotation_cards` table
- [ ] Create migration for `quotation_card_trades` table
- [ ] Create migration for `quotation_card_trade_items` table
- [ ] Create migration for `cards` table
- [ ] Create migration for `card_tokens` table
- [ ] Create `QuotationCard` model with relationships: `trades()` hasMany
- [ ] Create `QuotationCardTrade` model with relationships: `quotationCard()` belongsTo, `items()` hasMany, `leftItems()` / `rightItems()` filtered hasMany
- [ ] Create `QuotationCardTradeItem` model with relationships: `trade()` belongsTo, `tradeSide()` belongsTo, `tokenColor()` belongsTo
- [ ] Create `Card` model with relationships: `tokens()` hasMany
- [ ] Create `CardToken` model with relationships: `card()` belongsTo, `tokenColor()` belongsTo

**Tests (`tests/Feature/Models/GameComponentModelTest.php`):**
- [ ] Test `QuotationCard` has many `QuotationCardTrade`
- [ ] Test `QuotationCardTrade` has `leftItems()` and `rightItems()` returning correct sides
- [ ] Test `Card` has many `CardToken` and each card totals exactly 5 tokens
- [ ] Test `Card` `star_count` accepts values 0, 1, 2

### Phase 2.5: Game Component Seeders

- [ ] Create `QuotationCardSeeder` — seeds all 10 quotation cards with their trade equivalences (from `docs/jogo-original/Exemplo Quotations.jpg`)
- [ ] Create `CardSeeder` — seeds all 45 cards with their token requirements and star ratings (from `docs/jogo-original/Cards.jpg`)
- [ ] Update `DatabaseSeeder` to call all seeders in correct order

**Tests (`tests/Feature/Database/GameComponentSeederTest.php`):**
- [ ] Test that exactly 10 quotation cards are seeded
- [ ] Test each quotation card has at least 2 trade rows
- [ ] Test all trade items reference valid token colors and trade sides
- [ ] Test that exactly 45 cards are seeded
- [ ] Test each card has exactly 5 total tokens (summing quantities)
- [ ] Test cards have appropriate star_count distribution

### Phase 2.6: Match State Migrations & Models

- [ ] Create migration for `matches` table with all columns and indexes
- [ ] Create migration for `match_quotation_cards` pivot table
- [ ] Create migration for `match_compartments` table
- [ ] Create migration for `match_compartment_cards` table
- [ ] Create migration for `match_token_inventories` table
- [ ] Create migration for `match_turns` table
- [ ] Create `Match` model (namespaced as `GameMatch` or `App\Models\Match` with backtick escaping) with relationships:
    - `user()` belongsTo
    - `difficultyTier()` belongsTo
    - `matchStatus()` belongsTo
    - `matchResultType()` belongsTo
    - `currentParticipantType()` belongsTo
    - `quotationCards()` belongsToMany through pivot
    - `compartments()` hasMany
    - `tokenInventories()` hasMany
    - `turns()` hasMany
- [ ] Create `MatchQuotationCard` pivot model
- [ ] Create `MatchCompartment` model with relationships: `match()` belongsTo, `cards()` hasMany, scope `faceUpCard()`
- [ ] Create `MatchCompartmentCard` model with relationships: `compartment()` belongsTo, `card()` belongsTo, `purchasedByParticipantType()` belongsTo
- [ ] Create `MatchTokenInventory` model with relationships: `match()` belongsTo, `participantType()` belongsTo, `tokenColor()` belongsTo
- [ ] Create `MatchTurn` model with relationships and `$casts` for `action_data` as `array`/`json`
- [ ] Create `MatchFactory` for test convenience

**Tests (`tests/Feature/Models/MatchModelTest.php`):**
- [ ] Test `GameMatch` can be created with required fields
- [ ] Test `GameMatch` has correct relationships (user, difficultyTier, compartments, turns, tokenInventories)
- [ ] Test `MatchCompartment` `faceUpCard()` returns the first unpurchased card by position
- [ ] Test `MatchTokenInventory` unique constraint on (match, participant, color)
- [ ] Test `MatchTurn` `action_data` is cast to array

---

## Phase 3: Authentication

> Implements user registration, login, password reset, and logout.
> Reference: US-1.1, US-1.2, US-1.3, US-1.4

### Phase 3.1: Registration Page (US-1.1)

- [ ] Create Livewire full-page component `pages::auth.register`
- [ ] Create `RegisterForm` Livewire Form Object with validation:
    - `username`: required, string, min:3, max:20, unique:users
    - `email`: required, email, unique:users
    - `password`: required, min:8, confirmed
- [ ] Implement `save()` method: create user, send verification email, log in, redirect to arena
- [ ] Create Blade view using guest layout and design components
- [ ] Register route: `Route::livewire('/register', 'pages::auth.register')`

**Tests (`tests/Feature/Auth/RegistrationTest.php`):**
- [ ] Test registration page renders successfully (GET /register returns 200)
- [ ] Test user can register with valid data (username, email, password, password_confirmation)
- [ ] Test registration fails with duplicate username
- [ ] Test registration fails with duplicate email
- [ ] Test registration fails with short username (< 3 chars)
- [ ] Test registration fails with long username (> 20 chars)
- [ ] Test registration fails with short password (< 8 chars)
- [ ] Test registration fails with mismatched password confirmation
- [ ] Test registration fails with invalid email format
- [ ] Test user is redirected to arena dashboard after successful registration
- [ ] Test verification email is sent upon registration

### Phase 3.2: Login Page (US-1.2)

- [ ] Create Livewire full-page component `pages::auth.login`
- [ ] Create `LoginForm` Livewire Form Object with fields: `email`, `password`, `remember`
- [ ] Implement `authenticate()` method using Laravel's auth guard
- [ ] Create Blade view using guest layout
- [ ] Register route: `Route::livewire('/login', 'pages::auth.login')`
- [ ] Add "Forgot password?" link

**Tests (`tests/Feature/Auth/LoginTest.php`):**
- [ ] Test login page renders successfully (GET /login returns 200)
- [ ] Test user can log in with valid credentials
- [ ] Test login fails with wrong password
- [ ] Test login fails with non-existent email
- [ ] Test "remember me" sets persistent session
- [ ] Test authenticated user is redirected to arena dashboard
- [ ] Test already-authenticated user accessing /login is redirected to arena

### Phase 3.3: Password Reset (US-1.3)

- [ ] Create Livewire full-page component `pages::auth.forgot-password`
- [ ] Create Livewire full-page component `pages::auth.reset-password`
- [ ] Implement forgot password flow: validate email, send reset link
- [ ] Implement reset password flow: validate token, update password
- [ ] Create Blade views using guest layout
- [ ] Register routes for forgot-password and reset-password

**Tests (`tests/Feature/Auth/PasswordResetTest.php`):**
- [ ] Test forgot password page renders (GET /forgot-password returns 200)
- [ ] Test reset link is sent for valid email
- [ ] Test reset link is not sent for non-existent email (but no error shown for security)
- [ ] Test password can be reset with valid token
- [ ] Test password reset fails with invalid token
- [ ] Test password reset fails with expired token
- [ ] Test user is redirected to login after successful reset

### Phase 3.4: Logout (US-1.4)

- [ ] Add logout action to the authenticated layout sidebar
- [ ] Implement logout via POST route that destroys session
- [ ] Redirect to login page after logout

**Tests (`tests/Feature/Auth/LogoutTest.php`):**
- [ ] Test authenticated user can log out
- [ ] Test session is destroyed after logout
- [ ] Test user is redirected to login page after logout
- [ ] Test guest cannot access logout route

### Phase 3.5: Auth Middleware & Route Protection

- [ ] Apply `auth` middleware to all arena/game routes
- [ ] Apply `guest` middleware to login/register routes
- [ ] Set up `verified` middleware for email verification requirement
- [ ] Create email verification notice page
- [ ] Configure redirect paths (login → /arena, register → /arena)

**Tests (`tests/Feature/Auth/AuthMiddlewareTest.php`):**
- [ ] Test unauthenticated user is redirected to /login when accessing /arena
- [ ] Test authenticated user can access /arena
- [ ] Test unverified user is redirected to verification notice

---

## Phase 4: Arena Dashboard & Navigation

> Implements the main authenticated dashboard.
> Reference: US-2.1

### Phase 4.1: Arena Dashboard Page (US-2.1)

- [ ] Create Livewire full-page component `pages::arena.index`
- [ ] Display player username and current rank (from `player_ranks` via XP threshold)
- [ ] Display total XP with visual progress indicator
- [ ] Display "New Match" CTA button (links to match setup)
- [ ] If player has an ongoing match (status = `in_progress`), show "Resume Match" button
- [ ] Register route: `Route::livewire('/arena', 'pages::arena.index')`

**Tests (`tests/Feature/Arena/DashboardTest.php`):**
- [ ] Test arena dashboard renders for authenticated user (GET /arena returns 200)
- [ ] Test dashboard displays player username
- [ ] Test dashboard displays player rank based on XP
- [ ] Test dashboard shows "New Match" button
- [ ] Test dashboard shows "Resume Match" button when an in-progress match exists
- [ ] Test dashboard does NOT show "Resume Match" when no active match exists
- [ ] Test unauthenticated user is redirected to login

---

## Phase 5: Match Setup & Initialization

> Implements quotation card selection, difficulty tier selection, and match creation.
> Reference: US-3.1, US-3.2, US-3.3

### Phase 5.1: Match Setup Page (US-3.1, US-3.2)

- [ ] Create Livewire full-page component `pages::arena.match-setup`
- [ ] Load all 10 quotation cards with their trade equivalences (eager load trades + items)
- [ ] Display quotation cards as selectable cards with visual trade representations using `<x-token-dot>`
- [ ] Implement quotation card selection logic: toggle selection, enforce exactly 2 selected
- [ ] Display "X/2 Selected" counter
- [ ] Load and display 3 difficulty tiers as selectable cards with star ratings and XP rewards
- [ ] Implement difficulty tier selection: radio-style (exactly 1)
- [ ] "Start Match" button enabled only when 2 quotation cards + 1 tier selected
- [ ] Register route: `Route::livewire('/arena/new-match', 'pages::arena.match-setup')`

**Tests (`tests/Feature/Match/MatchSetupTest.php`):**
- [ ] Test match setup page renders for authenticated user
- [ ] Test all 10 quotation cards are displayed
- [ ] Test all 3 difficulty tiers are displayed
- [ ] Test player can select exactly 2 quotation cards
- [ ] Test selecting a 3rd quotation card deselects the first
- [ ] Test player can select 1 difficulty tier
- [ ] Test "Start Match" is disabled with 0 quotation cards selected
- [ ] Test "Start Match" is disabled with 1 quotation card selected
- [ ] Test "Start Match" is disabled with no difficulty tier selected
- [ ] Test "Start Match" is enabled with 2 quotation cards + 1 tier

### Phase 5.2: Match Initialization Service (US-3.3)

- [ ] Create `App\Services\MatchInitializationService`
- [ ] Method `createMatch(User $user, array $quotationCardIds, int $difficultyTierId): GameMatch`
    - Validate exactly 2 quotation card IDs
    - Validate difficulty tier exists
    - Create `matches` record with status = pending
    - Attach 2 quotation cards via pivot
    - Shuffle all 45 cards, pick 20, distribute into 4 compartments (5 cards each, positions 1–5)
    - Initialize 10 `match_token_inventories` rows (5 colors × 2 participants, all quantity 0)
    - Randomly assign first turn (player or AI)
    - Update status to `in_progress`, set `started_at`
    - Return the created match

**Tests (`tests/Feature/Services/MatchInitializationServiceTest.php`):**
- [ ] Test match is created with correct status (`in_progress`)
- [ ] Test exactly 2 quotation cards are attached to the match
- [ ] Test exactly 4 compartments are created
- [ ] Test each compartment has exactly 5 cards
- [ ] Test 20 unique cards are distributed (no duplicates)
- [ ] Test card positions within compartments are 1–5
- [ ] Test 10 token inventory rows are created (5 colors × 2 participants)
- [ ] Test all token inventories start at quantity 0
- [ ] Test first turn is randomly assigned (player or AI)
- [ ] Test `started_at` is set
- [ ] Test match is associated with the correct user
- [ ] Test match is associated with the correct difficulty tier
- [ ] Test service rejects fewer or more than 2 quotation card IDs
- [ ] Test service rejects invalid difficulty tier ID

### Phase 5.3: Start Match Action

- [ ] Wire the "Start Match" button in match setup to call `MatchInitializationService`
- [ ] On success, redirect player to the game board route: `/arena/match/{match}`
- [ ] Prevent starting a new match if player already has an `in_progress` match

**Tests (`tests/Feature/Match/StartMatchTest.php`):**
- [ ] Test clicking "Start Match" creates a match and redirects to game board
- [ ] Test player cannot start a new match while another is in progress
- [ ] Test player is redirected to existing match if one is in progress

---

## Phase 6: Game Board UI

> Implements the game board layout and visual components.
> Reference: US-4.1 and `docs/design/troca_jogo/screen.png`

### Phase 6.1: Game Board Page & Layout (US-4.1)

- [ ] Create Livewire full-page component `pages::arena.match-board`
- [ ] Register route: `Route::livewire('/arena/match/{match}', 'pages::arena.match-board')`
- [ ] Validate that the authenticated user owns the match
- [ ] Load match with all relationships (compartments, cards, token inventories, quotation cards, turns)
- [ ] Build the board layout matching the design:
    - **Left column:** Match summary (total actions, dice rolls, trades) + recent history log
    - **Center:** Current objective card + dice roll action area
    - **Bottom:** Active quotation cards (2 cards with trade options and "Execute Trade" buttons)
    - **Top:** Player token inventory (5 color counts)

**Tests (`tests/Feature/Match/GameBoardTest.php`):**
- [ ] Test game board page renders for match owner
- [ ] Test game board returns 403 for non-owner
- [ ] Test game board displays player token inventory (5 colors)
- [ ] Test game board displays 4 card compartments with face-up cards
- [ ] Test game board displays 2 active quotation cards
- [ ] Test game board displays whose turn it is (player or AI)
- [ ] Test game board displays match summary stats
- [ ] Test completed match redirects to results page

### Phase 6.2: Token Inventory Display Component

- [ ] Create Livewire component or Blade partial for token inventory
- [ ] Display each color with count and `<x-token-dot>` icon
- [ ] Highlight colors the player has > 0 of
- [ ] Show total token count with warning if approaching 10

### Phase 6.3: Card Compartment Display Component

- [ ] Create Blade component for a single compartment
- [ ] Show the face-up card (top unpurchased) with its 5 token requirements using `<x-token-dot>`
- [ ] Show star badge if card has stars
- [ ] Show compartment star bonus indicator when activated
- [ ] Show card count remaining in compartment
- [ ] Grey out compartment when all cards purchased

### Phase 6.4: Quotation Card Display Component

- [ ] Create Blade component for an active quotation card
- [ ] Display all trade rows with left/right sides using `<x-token-dot>`
- [ ] "Execute Trade" button for each trade row
- [ ] Visually distinguish available vs unavailable trades (based on player tokens)
- [ ] Label bonus/special trade types (if applicable per design)

### Phase 6.5: Match History Log Component

- [ ] Create Blade component for the recent history log
- [ ] Display last N actions from `match_turns` in reverse chronological order
- [ ] Format action descriptions: "Rolled dice — received 1 blue token", "Trade completed", "Purchased card", etc.
- [ ] Use icons/colors to distinguish action types

### Phase 6.6: Drag-and-Drop Token Return UI (wire:sort)

- [ ] Create Livewire component for returning excess tokens using **Livewire 4's `wire:sort`**
- [ ] Display player's current tokens as draggable items
- [ ] Allow player to drag tokens to a "return" zone to discard them
- [ ] Show live count of tokens remaining and how many need to be returned
- [ ] Confirm button to finalize token return
- [ ] This component is shown only when the player exceeds the 10-token limit (US-4.6)

---

## Phase 7: Core Game Mechanics

> Implements dice rolling, token trading, card purchasing, and scoring logic.
> Reference: US-4.2, US-4.3, US-4.4, US-4.5, US-4.6, US-4.9, US-5.1

### Phase 7.1: Dice Service (US-4.2, US-4.4)

- [ ] Create `App\Services\DiceService`
- [ ] Method `roll(): string` — returns one of: `red`, `green`, `white`, `yellow`, `blue`, `free` (equal probability)
- [ ] Method `applyRoll(GameMatch $match, int $participantTypeId, string $colorSlug): void`
    - Validates it's the correct participant's turn
    - Validates the participant hasn't already acted this turn
    - Adds 1 token of the given color to the participant's inventory
    - Creates a `match_turns` record with `roll_dice` action and JSON data
    - Sets `has_acted_this_turn = true`

**Tests (`tests/Feature/Services/DiceServiceTest.php`):**
- [ ] Test `roll()` returns a valid color slug or "free"
- [ ] Test `applyRoll()` increases token quantity by 1 for the correct color
- [ ] Test `applyRoll()` creates a turn record with correct action type and data
- [ ] Test `applyRoll()` sets `has_acted_this_turn` to true
- [ ] Test `applyRoll()` fails if participant has already acted this turn
- [ ] Test `applyRoll()` fails if it's not the participant's turn
- [ ] Test rolling "free" requires a separate color choice before applying

### Phase 7.2: Roll Dice Action (Livewire)

- [ ] Add `rollDice()` method to game board component
- [ ] Call `DiceService::roll()` and display the result
- [ ] If result is "free", show color selection modal (US-4.4)
- [ ] On color selection (or direct color result), call `DiceService::applyRoll()`
- [ ] Refresh token inventory and match history on the board
- [ ] Disable "Roll Dice" button after rolling

**Tests (`tests/Feature/Match/RollDiceActionTest.php`):**
- [ ] Test player can roll dice on their turn via Livewire
- [ ] Test rolling dice updates token inventory in the UI
- [ ] Test rolling dice disables further rolling this turn
- [ ] Test "free" roll shows color selection modal
- [ ] Test selecting a color after "free" roll applies the token correctly
- [ ] Test player cannot roll dice on AI's turn
- [ ] Test player cannot roll dice after already acting this turn

### Phase 7.3: Trade Service (US-4.3)

- [ ] Create `App\Services\TradeService`
- [ ] Method `executeTrade(GameMatch $match, int $participantTypeId, int $quotationCardTradeId, string $direction): void`
    - Validates it's the participant's turn and they haven't acted
    - Validates the quotation card trade belongs to one of the match's active quotation cards
    - Determines give/receive sides based on `$direction` (`left_to_right` or `right_to_left`)
    - Validates participant has sufficient tokens to give
    - Removes given tokens from inventory
    - Adds received tokens to inventory
    - Creates `match_turns` record with `trade` action and JSON data
    - Sets `has_acted_this_turn = true`

**Tests (`tests/Feature/Services/TradeServiceTest.php`):**
- [ ] Test valid trade removes correct tokens and adds correct tokens
- [ ] Test trade creates a turn record with correct action data
- [ ] Test trade fails with insufficient tokens (no changes made)
- [ ] Test trade fails if participant already acted this turn
- [ ] Test trade fails if it's not the participant's turn
- [ ] Test trade works in both directions (left-to-right and right-to-left)
- [ ] Test trade fails if quotation card is not active in this match
- [ ] Test only 1 trade allowed per turn

### Phase 7.4: Trade Action (Livewire)

- [ ] Add `executeTrade($quotationCardTradeId, $direction)` method to game board component
- [ ] Validate and call `TradeService::executeTrade()`
- [ ] Refresh token inventory, quotation card availability, and match history
- [ ] Show error flash if trade is invalid
- [ ] Disable trading after trade is executed

**Tests (`tests/Feature/Match/TradeActionTest.php`):**
- [ ] Test player can execute a valid trade via Livewire
- [ ] Test trade updates token inventory in the UI
- [ ] Test trading disables further actions this turn
- [ ] Test invalid trade shows error message
- [ ] Test player cannot trade on AI's turn

### Phase 7.5: Scoring Service (US-5.1, US-4.9)

- [ ] Create `App\Services\ScoringService`
- [ ] Method `calculatePoints(int $remainingTokens, int $cardStarCount, int $compartmentStarBonuses): int`
    - Compute effective star count = `cardStarCount + compartmentStarBonuses` (capped at 2)
    - Look up `scoring_rules` table for the matching bracket and star count
    - Return the points
- [ ] Method `getActiveStarBonuses(GameMatch $match): int`
    - Count compartments with `is_star_bonus_active = true`

**Tests (`tests/Feature/Services/ScoringServiceTest.php`):**
- [ ] Test all 12 scoring combinations (4 token brackets × 3 star levels) return correct points
- [ ] Test 0 remaining tokens with 0 stars = 5 points
- [ ] Test 0 remaining tokens with 2 stars = 12 points
- [ ] Test 3+ remaining tokens with 0 stars = 1 point
- [ ] Test compartment star bonus adds to card star count
- [ ] Test effective star count is capped at 2
- [ ] Test `getActiveStarBonuses()` correctly counts emptied compartments

### Phase 7.6: Card Purchase Service (US-4.5, US-4.9)

- [ ] Create `App\Services\CardPurchaseService`
- [ ] Method `purchaseCard(GameMatch $match, int $participantTypeId, int $matchCompartmentCardId): int`
    - Validates the participant has acted this turn (must roll or trade first)
    - Validates the card is face-up (lowest position unpurchased in its compartment)
    - Validates participant has all required tokens
    - Removes the 5 matching tokens from inventory
    - Marks card as purchased, sets `purchased_by_participant_type_id` and `purchased_at`
    - Calculates points via `ScoringService` (considering star bonuses)
    - Stores `points_scored` on the card record
    - Updates match `player_score` or `ai_score`
    - Updates match `player_cards_purchased` or `ai_cards_purchased`
    - If compartment is now empty, activate `is_star_bonus_active` and increment `compartments_emptied`
    - Creates `match_turns` record with `purchase_card` action
    - Returns points scored

**Tests (`tests/Feature/Services/CardPurchaseServiceTest.php`):**
- [ ] Test successful purchase removes correct tokens from inventory
- [ ] Test successful purchase marks card as purchased with correct participant
- [ ] Test points are calculated correctly and stored on the card record
- [ ] Test match score is updated for the correct participant
- [ ] Test match `player_cards_purchased` / `ai_cards_purchased` counter increments
- [ ] Test purchase fails if participant hasn't acted this turn (no roll/trade before purchase)
- [ ] Test purchase fails if card is not face-up
- [ ] Test purchase fails if participant lacks required tokens
- [ ] Test compartment star bonus activates when last card in compartment is purchased
- [ ] Test `compartments_emptied` increments when a compartment becomes empty
- [ ] Test star bonus correctly affects scoring of subsequent purchases
- [ ] Test purchase creates turn record with correct action data

### Phase 7.7: Card Purchase Action (Livewire)

- [ ] Add `purchaseCard($matchCompartmentCardId)` method to game board component
- [ ] Call `CardPurchaseService::purchaseCard()`
- [ ] Display points earned animation/flash
- [ ] Refresh all board state (inventory, compartments, scores, history)
- [ ] Show purchasable cards with "Buy" button only when eligible (has acted + has tokens)

**Tests (`tests/Feature/Match/PurchaseCardActionTest.php`):**
- [ ] Test player can purchase an eligible card via Livewire
- [ ] Test purchase updates score display on the board
- [ ] Test purchase removes tokens from inventory display
- [ ] Test next card in compartment becomes visible after purchase
- [ ] Test "Buy" button only appears for eligible cards

### Phase 7.8: Token Limit Enforcement (US-4.6)

- [ ] Create `App\Services\TokenLimitService`
- [ ] Method `isOverLimit(GameMatch $match, int $participantTypeId): bool` — checks if total tokens > 10
- [ ] Method `getExcessCount(GameMatch $match, int $participantTypeId): int`
- [ ] Method `returnTokens(GameMatch $match, int $participantTypeId, array $tokensToReturn): void`
    - Validates total returned equals the excess
    - Validates participant has the tokens to return
    - Decrements token inventories
    - Creates `match_turns` record with `return_tokens` action
- [ ] Integrate token limit check into end-turn flow (block ending turn if over limit)

**Tests (`tests/Feature/Services/TokenLimitServiceTest.php`):**
- [ ] Test `isOverLimit()` returns true when total tokens > 10
- [ ] Test `isOverLimit()` returns false when total tokens <= 10
- [ ] Test `getExcessCount()` returns correct count
- [ ] Test `returnTokens()` decreases correct token inventories
- [ ] Test `returnTokens()` fails if returned amount doesn't match excess
- [ ] Test `returnTokens()` creates turn record with correct data
- [ ] Test `returnTokens()` fails if participant doesn't have the tokens specified

---

## Phase 8: Turn System & AI Stub

> Implements turn management, end turn flow, and the AI opponent stub.
> Reference: US-4.7, US-4.8, US-5.2

### Phase 8.1: Turn Management Service (US-4.7)

- [ ] Create `App\Services\TurnService`
- [ ] Method `endTurn(GameMatch $match): void`
    - Validates current participant has acted this turn
    - Validates current participant is not over the token limit
    - Checks game end condition (2 compartments emptied) — if met, finalize match (delegate to `MatchFinalizationService`)
    - If game continues:
        - Toggle `current_participant_type_id` (player ↔ AI)
        - Increment `current_turn_number`
        - Reset `has_acted_this_turn` to false
        - If new turn belongs to AI, trigger AI stub
- [ ] Method `getCurrentTurnState(GameMatch $match): array` — returns whose turn, has acted, can end turn, is over limit

**Tests (`tests/Feature/Services/TurnServiceTest.php`):**
- [ ] Test `endTurn()` switches participant from player to AI
- [ ] Test `endTurn()` switches participant from AI to player
- [ ] Test `endTurn()` increments turn number
- [ ] Test `endTurn()` resets `has_acted_this_turn` to false
- [ ] Test `endTurn()` fails if participant hasn't acted
- [ ] Test `endTurn()` fails if participant is over token limit
- [ ] Test `endTurn()` triggers game end when 2 compartments are emptied
- [ ] Test `endTurn()` triggers AI stub when turn passes to AI

### Phase 8.2: End Turn Action (Livewire)

- [ ] Add `endTurn()` method to game board component
- [ ] Call `TurnService::endTurn()`
- [ ] If game ends, redirect to match results page
- [ ] If AI turn, show "AI thinking..." state, execute AI stub, then refresh board
- [ ] "End Turn" button visibility: shown only after player has acted and is not over token limit

**Tests (`tests/Feature/Match/EndTurnActionTest.php`):**
- [ ] Test player can end turn after acting via Livewire
- [ ] Test "End Turn" button is hidden before player acts
- [ ] Test "End Turn" button is hidden when player is over token limit
- [ ] Test ending turn transitions to AI turn and back
- [ ] Test ending turn redirects to results when game end condition is met

### Phase 8.3: AI Opponent Stub Service (US-4.8)

- [ ] Create `App\Services\AiOpponentService`
- [ ] Method `executeTurn(GameMatch $match): void`
    - Receives full game state
    - **Stub implementation:** always rolls the dice with a random color result
    - Applies the roll via `DiceService::applyRoll()`
    - Evaluates if AI can purchase any face-up card — if so, purchases the first eligible one via `CardPurchaseService`
    - Checks token limit — if over, returns random excess tokens via `TokenLimitService`
    - Ends the AI's turn (toggles back to player)
- [ ] Method is designed as a clear extension point for future AI strategy implementation
- [ ] Accepts `DifficultyTier` as parameter for future difficulty-based behavior

**Tests (`tests/Feature/Services/AiOpponentServiceTest.php`):**
- [ ] Test AI stub executes a dice roll on its turn
- [ ] Test AI stub creates a turn record with correct participant type (AI)
- [ ] Test AI stub adds a token to AI's inventory
- [ ] Test AI stub purchases a card when it has the required tokens
- [ ] Test AI stub respects the 10-token limit
- [ ] Test AI stub returns turn to player after completing its action
- [ ] Test AI stub receives difficulty tier parameter
- [ ] Test AI stub handles "free" dice result (picks a random color)

### Phase 8.4: Game End Detection (US-5.2)

- [ ] Add game end check to `TurnService::endTurn()` and `CardPurchaseService::purchaseCard()`
- [ ] When `compartments_emptied >= 2`, trigger match finalization
- [ ] No further turns allowed after game end

**Tests (`tests/Feature/Match/GameEndTest.php`):**
- [ ] Test game does NOT end when only 1 compartment is emptied
- [ ] Test game ends when 2nd compartment becomes empty
- [ ] Test no further turns can be taken after game ends
- [ ] Test match status changes to `completed` on game end
- [ ] Test game end is triggered regardless of whose turn it is

---

## Phase 9: Match Results, XP & Leaderboard

> Implements post-game results, XP progression, and the leaderboard.
> Reference: US-5.3, US-6.1, US-6.2

### Phase 9.1: Match Finalization Service (US-5.3)

- [ ] Create `App\Services\MatchFinalizationService`
- [ ] Method `finalizeMatch(GameMatch $match): void`
    - Determine winner: compare `player_score` vs `ai_score`
    - Apply tiebreaker: fewer cards purchased wins; if still tied, result = draw
    - Set `match_result_type_id`
    - Calculate XP: `base_xp_reward` from difficulty tier + `win_bonus_xp` if player won
    - Set `xp_earned` on match
    - Add XP to user's `total_xp`
    - Update user's `player_rank_id` based on new total XP
    - Set match status to `completed`, set `completed_at`

**Tests (`tests/Feature/Services/MatchFinalizationServiceTest.php`):**
- [ ] Test player wins when player_score > ai_score
- [ ] Test AI wins when ai_score > player_score
- [ ] Test tiebreaker: fewer cards purchased wins on score tie
- [ ] Test draw when both scores and card counts are equal
- [ ] Test XP is calculated correctly: base + win bonus
- [ ] Test XP is added to user's total_xp
- [ ] Test user's player_rank_id is updated when crossing a rank threshold
- [ ] Test match status is set to `completed`
- [ ] Test `completed_at` timestamp is set
- [ ] Test losing player still earns base XP (no win bonus)

### Phase 9.2: Match Results Page (US-5.3)

- [ ] Create Livewire full-page component `pages::arena.match-results`
- [ ] Register route: `Route::livewire('/arena/match/{match}/results', 'pages::arena.match-results')`
- [ ] Display:
    - Winner announcement (Player Win / AI Win / Draw)
    - Player final score with per-card breakdown (card, tokens remaining, star count, points)
    - AI final score with per-card breakdown
    - Total cards purchased by each side
    - Difficulty tier played
    - XP earned
- [ ] "Play Again" button → redirect to match setup
- [ ] "Back to Arena" button → redirect to dashboard

**Tests (`tests/Feature/Match/MatchResultsTest.php`):**
- [ ] Test results page renders for completed match
- [ ] Test results page shows correct winner
- [ ] Test results page shows player and AI scores
- [ ] Test results page shows XP earned
- [ ] Test results page returns 404 for in-progress match
- [ ] Test results page returns 403 for non-owner
- [ ] Test "Play Again" link navigates to match setup
- [ ] Test "Back to Arena" link navigates to dashboard

### Phase 9.3: Leaderboard Page (US-6.1)

- [ ] Create Livewire full-page component `pages::leaderboard.index`
- [ ] Register route: `Route::livewire('/leaderboard', 'pages::leaderboard.index')`
- [ ] Query users ordered by `total_xp` descending with pagination
- [ ] For each player, compute:
    - Rank position
    - Username
    - Player rank name (from `player_ranks`)
    - Total matches played (count of completed matches)
    - Total wins (count of matches with `player_win` result)
    - Total XP
- [ ] Highlight the current authenticated player's row
- [ ] Paginate results (e.g., 20 per page)

**Tests (`tests/Feature/Leaderboard/LeaderboardTest.php`):**
- [ ] Test leaderboard page renders for authenticated user
- [ ] Test players are sorted by total_xp descending
- [ ] Test current player's row is visually marked
- [ ] Test leaderboard shows correct match counts and win counts
- [ ] Test leaderboard paginates correctly
- [ ] Test leaderboard handles users with 0 matches

### Phase 9.4: XP & Rank Progression (US-6.2)

- [ ] Ensure `MatchFinalizationService` correctly updates XP (from Phase 9.1)
- [ ] Create an Eloquent Observer or integrate into finalization: recalculate `player_rank_id` whenever `total_xp` changes
- [ ] Display XP and rank on the arena dashboard (from Phase 4.1)
- [ ] Display XP and rank on the leaderboard (from Phase 9.3)

**Tests (`tests/Feature/Progression/XpProgressionTest.php`):**
- [ ] Test user starts at Bronze rank (0 XP)
- [ ] Test user rank updates to Silver at 500 XP
- [ ] Test user rank updates to Gold at 1500 XP
- [ ] Test user rank updates to Platinum at 3500 XP
- [ ] Test user rank updates to Diamond at 7000 XP
- [ ] Test rank doesn't downgrade (XP is cumulative, never decreases)
- [ ] Test XP from multiple matches accumulates correctly

---

## Phase 10: Player Settings

> Implements the player account settings page.
> Reference: US-7.1

### Phase 10.1: Settings Page (US-7.1)

- [ ] Create Livewire full-page component `pages::settings.index`
- [ ] Create `UpdateProfileForm` Livewire Form Object with:
    - `username`: required, string, min:3, max:20, unique (excluding current user)
    - `email`: required, email, unique (excluding current user)
- [ ] Create `UpdatePasswordForm` Livewire Form Object with:
    - `current_password`: required, must match current password
    - `password`: required, min:8, confirmed
- [ ] Implement profile update: save username/email, trigger re-verification if email changes
- [ ] Implement password update: validate current password, update password
- [ ] Register route: `Route::livewire('/settings', 'pages::settings.index')`
- [ ] Show success flash messages on update

**Tests (`tests/Feature/Settings/UpdateProfileTest.php`):**
- [ ] Test settings page renders for authenticated user
- [ ] Test user can update username
- [ ] Test user can update email (triggers re-verification)
- [ ] Test username update fails with duplicate username
- [ ] Test email update fails with duplicate email
- [ ] Test username validation (min 3, max 20)

**Tests (`tests/Feature/Settings/UpdatePasswordTest.php`):**
- [ ] Test user can update password with correct current password
- [ ] Test password update fails with wrong current password
- [ ] Test password update fails with short new password (< 8 chars)
- [ ] Test password update fails with mismatched confirmation
- [ ] Test password is actually changed (can log in with new password)

---

## Appendix: Phase Summary

| Phase | Description | User Stories | Status |
|-------|-------------|--------------|--------|
| Phase 1 | Frontend Foundation (No Tests) | — | Pending |
| Phase 2 | Database, Models & Seeders | — | Pending |
| Phase 3 | Authentication | US-1.1, US-1.2, US-1.3, US-1.4 | Pending |
| Phase 4 | Arena Dashboard & Navigation | US-2.1 | Pending |
| Phase 5 | Match Setup & Initialization | US-3.1, US-3.2, US-3.3 | Pending |
| Phase 6 | Game Board UI | US-4.1 | Pending |
| Phase 7 | Core Game Mechanics | US-4.2–4.6, US-4.9, US-5.1 | Pending |
| Phase 8 | Turn System & AI Stub | US-4.7, US-4.8, US-5.2 | Pending |
| Phase 9 | Match Results, XP & Leaderboard | US-5.3, US-6.1, US-6.2 | Pending |
| Phase 10 | Player Settings | US-7.1 | Pending |

---

## Appendix: Test File Index

| Test File | Phase | Coverage |
|-----------|-------|----------|
| `tests/Feature/Database/LookupTableSeederTest.php` | 2.1 | Lookup table seeding |
| `tests/Feature/Models/LookupModelTest.php` | 2.2 | Rank/scoring model logic |
| `tests/Feature/Models/UserModelTest.php` | 2.3 | User model & relationships |
| `tests/Feature/Models/GameComponentModelTest.php` | 2.4 | Quotation cards, cards models |
| `tests/Feature/Database/GameComponentSeederTest.php` | 2.5 | Game data seeding |
| `tests/Feature/Models/MatchModelTest.php` | 2.6 | Match & related models |
| `tests/Feature/Auth/RegistrationTest.php` | 3.1 | Player registration |
| `tests/Feature/Auth/LoginTest.php` | 3.2 | Player login |
| `tests/Feature/Auth/PasswordResetTest.php` | 3.3 | Password reset flow |
| `tests/Feature/Auth/LogoutTest.php` | 3.4 | Logout |
| `tests/Feature/Auth/AuthMiddlewareTest.php` | 3.5 | Route protection |
| `tests/Feature/Arena/DashboardTest.php` | 4.1 | Arena dashboard |
| `tests/Feature/Match/MatchSetupTest.php` | 5.1 | Quotation & tier selection |
| `tests/Feature/Services/MatchInitializationServiceTest.php` | 5.2 | Match creation service |
| `tests/Feature/Match/StartMatchTest.php` | 5.3 | Start match action |
| `tests/Feature/Match/GameBoardTest.php` | 6.1 | Game board rendering |
| `tests/Feature/Services/DiceServiceTest.php` | 7.1 | Dice rolling logic |
| `tests/Feature/Match/RollDiceActionTest.php` | 7.2 | Roll dice Livewire action |
| `tests/Feature/Services/TradeServiceTest.php` | 7.3 | Trade execution logic |
| `tests/Feature/Match/TradeActionTest.php` | 7.4 | Trade Livewire action |
| `tests/Feature/Services/ScoringServiceTest.php` | 7.5 | Scoring calculation |
| `tests/Feature/Services/CardPurchaseServiceTest.php` | 7.6 | Card purchase logic |
| `tests/Feature/Match/PurchaseCardActionTest.php` | 7.7 | Purchase Livewire action |
| `tests/Feature/Services/TokenLimitServiceTest.php` | 7.8 | Token limit enforcement |
| `tests/Feature/Services/TurnServiceTest.php` | 8.1 | Turn management |
| `tests/Feature/Match/EndTurnActionTest.php` | 8.2 | End turn Livewire action |
| `tests/Feature/Services/AiOpponentServiceTest.php` | 8.3 | AI stub |
| `tests/Feature/Match/GameEndTest.php` | 8.4 | Game end detection |
| `tests/Feature/Services/MatchFinalizationServiceTest.php` | 9.1 | Match finalization |
| `tests/Feature/Match/MatchResultsTest.php` | 9.2 | Results page |
| `tests/Feature/Leaderboard/LeaderboardTest.php` | 9.3 | Leaderboard |
| `tests/Feature/Progression/XpProgressionTest.php` | 9.4 | XP & rank progression |
| `tests/Feature/Settings/UpdateProfileTest.php` | 10.1 | Profile settings |
| `tests/Feature/Settings/UpdatePasswordTest.php` | 10.1 | Password settings |
