# TROCA — Database Schema (DBML)

## Overview

This schema supports the TROCA game application following Laravel conventions. All categorical/enumerable fields use lookup tables with foreign keys instead of string/enum columns. Tables are organized into four groups:

1. **Lookup Tables** — Predefined, seeded values for statuses, types, and categories
2. **Authentication** — User accounts and progression
3. **Game Components** — Seeded game data (cards, quotation cards, scoring rules)
4. **Match State** — Per-match runtime data (turns, inventories, compartments)

---

```dbml
// ============================================================
// LOOKUP / AUXILIARY TABLES
// ============================================================

Table token_colors {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'red, green, white, yellow, blue']
  slug varchar(50) [not null, unique]
  hex_code varchar(7) [not null, note: 'Hex color for UI rendering, e.g. #FF0000']
  created_at timestamp
  updated_at timestamp
}

Table difficulty_tiers {
  id bigint [pk, increment]
  name varchar(100) [not null, unique, note: 'Padrão Primário, Cadeia Cruzada, Mestre do Caos']
  slug varchar(100) [not null, unique]
  description text [null]
  star_count smallint [not null, default: 1, note: 'Visual star indicator: 1, 2, or 3']
  base_xp_reward int [not null, note: 'Base XP granted for completing a match at this tier']
  win_bonus_xp int [not null, default: 0, note: 'Additional XP granted on win']
  sort_order smallint [not null, default: 0]
  created_at timestamp
  updated_at timestamp
}

Table match_statuses {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'pending, in_progress, completed, abandoned']
  slug varchar(50) [not null, unique]
  created_at timestamp
  updated_at timestamp
}

Table match_result_types {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'player_win, ai_win, draw']
  slug varchar(50) [not null, unique]
  created_at timestamp
  updated_at timestamp
}

Table turn_action_types {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'roll_dice, trade, purchase_card, return_tokens']
  slug varchar(50) [not null, unique]
  created_at timestamp
  updated_at timestamp
}

Table participant_types {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'player, ai']
  slug varchar(50) [not null, unique]
  created_at timestamp
  updated_at timestamp
}

Table trade_sides {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'left, right — bidirectional trade equivalence sides']
  slug varchar(50) [not null, unique]
  created_at timestamp
  updated_at timestamp
}

Table player_ranks {
  id bigint [pk, increment]
  name varchar(50) [not null, unique, note: 'e.g. Bronze, Silver, Gold, Platinum, Diamond']
  slug varchar(50) [not null, unique]
  min_xp int [not null, default: 0, note: 'Minimum XP threshold to reach this rank']
  sort_order smallint [not null, default: 0]
  created_at timestamp
  updated_at timestamp
}

// ============================================================
// AUTHENTICATION & USER PROGRESSION
// ============================================================

Table users {
  id bigint [pk, increment]
  username varchar(20) [not null, unique]
  email varchar(255) [not null, unique]
  email_verified_at timestamp [null]
  password varchar(255) [not null]
  total_xp int [not null, default: 0]
  player_rank_id bigint [null, ref: > player_ranks.id]
  remember_token varchar(100) [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    total_xp [name: 'idx_users_total_xp', note: 'Leaderboard sorting']
    player_rank_id [name: 'idx_users_player_rank_id']
  }
}

// Laravel default tables (included for completeness)

Table password_reset_tokens {
  email varchar(255) [pk]
  token varchar(255) [not null]
  created_at timestamp [null]
}

Table sessions {
  id varchar(255) [pk]
  user_id bigint [null, ref: > users.id]
  ip_address varchar(45) [null]
  user_agent text [null]
  payload longtext [not null]
  last_activity int [not null]

  indexes {
    user_id [name: 'idx_sessions_user_id']
    last_activity [name: 'idx_sessions_last_activity']
  }
}

// ============================================================
// GAME COMPONENTS (SEEDED DATA)
// ============================================================

// --- Quotation Cards (Cartões de Cotação) ---
// 10 predefined cards, each defining multiple bidirectional trade equivalences.
// Structure: QuotationCard -> has many Trades -> each Trade has Items on left/right sides.
// Example: Card #1, Trade row: 1 red (left) = 1 blue + 1 green + 1 yellow + 1 white (right)
// The player can trade in either direction (left-to-right or right-to-left).

Table quotation_cards {
  id bigint [pk, increment]
  number smallint [not null, unique, note: 'Card number 1–10, matches physical card']
  name varchar(100) [not null, note: 'Display name, e.g. Green to Blue']
  description text [null]
  created_at timestamp
  updated_at timestamp
}

Table quotation_card_trades {
  id bigint [pk, increment]
  quotation_card_id bigint [not null, ref: > quotation_cards.id]
  sort_order smallint [not null, default: 0, note: 'Display order within the card']
  created_at timestamp
  updated_at timestamp

  indexes {
    quotation_card_id [name: 'idx_qct_quotation_card_id']
  }
}

Table quotation_card_trade_items {
  id bigint [pk, increment]
  quotation_card_trade_id bigint [not null, ref: > quotation_card_trades.id]
  trade_side_id bigint [not null, ref: > trade_sides.id, note: 'left or right side of the equivalence']
  token_color_id bigint [not null, ref: > token_colors.id]
  quantity smallint [not null, default: 1]
  created_at timestamp
  updated_at timestamp

  indexes {
    quotation_card_trade_id [name: 'idx_qcti_trade_id']
    (quotation_card_trade_id, trade_side_id) [name: 'idx_qcti_trade_side']
  }
}

// --- Game Cards (Cartas) ---
// 45 cards, each requiring exactly 5 colored tokens to purchase.
// Some cards have 1 or 2 printed stars that affect scoring.

Table cards {
  id bigint [pk, increment]
  number smallint [not null, unique, note: 'Card number 1–45 for identification']
  star_count smallint [not null, default: 0, note: '0 = normal, 1 = one star, 2 = two stars']
  created_at timestamp
  updated_at timestamp
}

Table card_tokens {
  id bigint [pk, increment]
  card_id bigint [not null, ref: > cards.id]
  token_color_id bigint [not null, ref: > token_colors.id]
  quantity smallint [not null, default: 1, note: 'How many tokens of this color the card requires']
  created_at timestamp
  updated_at timestamp

  indexes {
    card_id [name: 'idx_card_tokens_card_id']
    (card_id, token_color_id) [unique, name: 'uniq_card_tokens_card_color']
  }
}

// --- Scoring Rules ---
// Fixed 4x3 matrix mapping (remaining_tokens, effective_star_count) -> points.
// Seeded once; queried during card purchase to calculate points.

Table scoring_rules {
  id bigint [pk, increment]
  min_remaining_tokens smallint [not null, note: 'Minimum tokens for this bracket (inclusive)']
  max_remaining_tokens smallint [null, note: 'Maximum tokens for this bracket (inclusive). NULL = no upper limit (3+)']
  star_count smallint [not null, note: '0 = normal, 1 = one star, 2 = two stars']
  points smallint [not null]
  created_at timestamp
  updated_at timestamp

  indexes {
    (star_count, min_remaining_tokens) [name: 'idx_scoring_star_tokens']
  }

  note: '''
  Seeded data:
  (min=3, max=NULL, stars=0) -> 1 pt  | (stars=1) -> 2 pt  | (stars=2) -> 3 pt
  (min=2, max=2,    stars=0) -> 2 pt  | (stars=1) -> 3 pt  | (stars=2) -> 5 pt
  (min=1, max=1,    stars=0) -> 3 pt  | (stars=1) -> 5 pt  | (stars=2) -> 8 pt
  (min=0, max=0,    stars=0) -> 5 pt  | (stars=1) -> 8 pt  | (stars=2) -> 12 pt
  '''
}

// ============================================================
// MATCH STATE (RUNTIME DATA)
// ============================================================

// --- Match ---
// Core match record. One per game session.

Table matches {
  id bigint [pk, increment]
  user_id bigint [not null, ref: > users.id, note: 'The human player']
  difficulty_tier_id bigint [not null, ref: > difficulty_tiers.id]
  match_status_id bigint [not null, ref: > match_statuses.id]
  match_result_type_id bigint [null, ref: > match_result_types.id, note: 'NULL while match is in progress']
  current_turn_number smallint [not null, default: 0]
  current_participant_type_id bigint [null, ref: > participant_types.id, note: 'Whose turn it is. NULL before match starts']
  has_acted_this_turn boolean [not null, default: false, note: 'Whether the current participant has rolled/traded this turn']
  player_score int [not null, default: 0]
  ai_score int [not null, default: 0]
  player_cards_purchased smallint [not null, default: 0]
  ai_cards_purchased smallint [not null, default: 0]
  compartments_emptied smallint [not null, default: 0, note: 'Count of emptied compartments. Game ends at 2']
  xp_earned int [not null, default: 0, note: 'XP awarded to player at match end']
  started_at timestamp [null]
  completed_at timestamp [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    user_id [name: 'idx_matches_user_id']
    match_status_id [name: 'idx_matches_status_id']
    (user_id, match_status_id) [name: 'idx_matches_user_status']
  }
}

// --- Match Quotation Cards (Pivot) ---
// Exactly 2 quotation cards selected per match.

Table match_quotation_cards {
  id bigint [pk, increment]
  match_id bigint [not null, ref: > matches.id]
  quotation_card_id bigint [not null, ref: > quotation_cards.id]
  created_at timestamp
  updated_at timestamp

  indexes {
    match_id [name: 'idx_mqc_match_id']
    (match_id, quotation_card_id) [unique, name: 'uniq_mqc_match_quotation']
  }
}

// --- Match Compartments ---
// 4 compartments per match, each holding a stack of 5 cards.

Table match_compartments {
  id bigint [pk, increment]
  match_id bigint [not null, ref: > matches.id]
  position smallint [not null, note: '1–4, compartment position on the board']
  is_star_bonus_active boolean [not null, default: false, note: 'Activated when all cards in this compartment are purchased']
  created_at timestamp
  updated_at timestamp

  indexes {
    match_id [name: 'idx_mc_match_id']
    (match_id, position) [unique, name: 'uniq_mc_match_position']
  }
}

// --- Match Compartment Cards ---
// 5 cards per compartment (20 total per match). Tracks purchase state.

Table match_compartment_cards {
  id bigint [pk, increment]
  match_compartment_id bigint [not null, ref: > match_compartments.id]
  card_id bigint [not null, ref: > cards.id]
  position smallint [not null, note: '1–5, stack order. Position 1 = top (face-up)']
  is_purchased boolean [not null, default: false]
  purchased_by_participant_type_id bigint [null, ref: > participant_types.id, note: 'NULL if not yet purchased']
  points_scored smallint [null, note: 'Points awarded for this specific card purchase']
  purchased_at timestamp [null]
  created_at timestamp
  updated_at timestamp

  indexes {
    match_compartment_id [name: 'idx_mcc_compartment_id']
    (match_compartment_id, position) [unique, name: 'uniq_mcc_compartment_position']
  }
}

// --- Match Token Inventories ---
// Tracks current token counts for each participant (player and AI) during a match.
// One row per (match, participant, color) combination = 10 rows per match (5 colors x 2 participants).

Table match_token_inventories {
  id bigint [pk, increment]
  match_id bigint [not null, ref: > matches.id]
  participant_type_id bigint [not null, ref: > participant_types.id]
  token_color_id bigint [not null, ref: > token_colors.id]
  quantity smallint [not null, default: 0]
  created_at timestamp
  updated_at timestamp

  indexes {
    (match_id, participant_type_id) [name: 'idx_mti_match_participant']
    (match_id, participant_type_id, token_color_id) [unique, name: 'uniq_mti_match_participant_color']
  }
}

// --- Match Turns ---
// Chronological log of every action taken during a match.
// The action_data JSON column stores variable details depending on the action type.

Table match_turns {
  id bigint [pk, increment]
  match_id bigint [not null, ref: > matches.id]
  turn_number smallint [not null]
  participant_type_id bigint [not null, ref: > participant_types.id]
  turn_action_type_id bigint [not null, ref: > turn_action_types.id]
  action_data json [null, note: 'Variable structure per action type (see note below)']
  created_at timestamp
  updated_at timestamp

  indexes {
    match_id [name: 'idx_mt_match_id']
    (match_id, turn_number) [name: 'idx_mt_match_turn']
  }

  note: '''
  action_data JSON structures by turn_action_type:

  roll_dice:
  {
    "dice_result": "blue",        // color slug or "free"
    "color_chosen": "red",        // only present when dice_result = "free"
    "token_color_id": 1
  }

  trade:
  {
    "quotation_card_id": 3,
    "quotation_card_trade_id": 7,
    "tokens_given": { "red": 1 },
    "tokens_received": { "blue": 1, "green": 1, "yellow": 1, "white": 1 }
  }

  purchase_card:
  {
    "match_compartment_card_id": 12,
    "card_id": 5,
    "compartment_position": 2,
    "tokens_spent": { "red": 1, "blue": 2, "green": 1, "white": 1 },
    "remaining_tokens_after": 3,
    "effective_star_count": 1,
    "points_scored": 2
  }

  return_tokens:
  {
    "reason": "excess",           // token limit enforcement
    "tokens_returned": { "yellow": 2 }
  }
  '''
}
```

---

## Entity Relationship Summary

```
users ──────────────── player_ranks (many-to-one)
  │
  └── matches ──────── difficulty_tiers (many-to-one)
        │               match_statuses (many-to-one)
        │               match_result_types (many-to-one)
        │               participant_types (many-to-one, current turn)
        │
        ├── match_quotation_cards ──── quotation_cards (many-to-many pivot)
        │                                │
        │                                └── quotation_card_trades
        │                                      └── quotation_card_trade_items
        │                                            ├── trade_sides
        │                                            └── token_colors
        │
        ├── match_compartments
        │     └── match_compartment_cards ── cards
        │                                     └── card_tokens ── token_colors
        │
        ├── match_token_inventories ──── participant_types
        │                                token_colors
        │
        └── match_turns ──────────────── participant_types
                                         turn_action_types
```

---

## Seeded Data Reference

### token_colors
| id | name | slug | hex_code |
|----|------|------|----------|
| 1 | Red | red | #EF4444 |
| 2 | Green | green | #22C55E |
| 3 | White | white | #F8FAFC |
| 4 | Yellow | yellow | #EAB308 |
| 5 | Blue | blue | #3B82F6 |

### difficulty_tiers
| id | name | slug | star_count | base_xp_reward | win_bonus_xp |
|----|------|------|------------|----------------|--------------|
| 1 | Padrão Primário | padrao-primario | 1 | 100 | 50 |
| 2 | Cadeia Cruzada | cadeia-cruzada | 2 | 200 | 100 |
| 3 | Mestre do Caos | mestre-do-caos | 3 | 350 | 150 |

### match_statuses
| id | name | slug |
|----|------|------|
| 1 | Pending | pending |
| 2 | In Progress | in_progress |
| 3 | Completed | completed |
| 4 | Abandoned | abandoned |

### match_result_types
| id | name | slug |
|----|------|------|
| 1 | Player Win | player_win |
| 2 | AI Win | ai_win |
| 3 | Draw | draw |

### turn_action_types
| id | name | slug |
|----|------|------|
| 1 | Roll Dice | roll_dice |
| 2 | Trade | trade |
| 3 | Purchase Card | purchase_card |
| 4 | Return Tokens | return_tokens |

### participant_types
| id | name | slug |
|----|------|------|
| 1 | Player | player |
| 2 | AI | ai |

### trade_sides
| id | name | slug |
|----|------|------|
| 1 | Left | left |
| 2 | Right | right |

### player_ranks
| id | name | slug | min_xp |
|----|------|------|--------|
| 1 | Bronze | bronze | 0 |
| 2 | Silver | silver | 500 |
| 3 | Gold | gold | 1500 |
| 4 | Platinum | platinum | 3500 |
| 5 | Diamond | diamond | 7000 |

### scoring_rules
| id | min_remaining_tokens | max_remaining_tokens | star_count | points |
|----|---------------------|---------------------|------------|--------|
| 1 | 3 | NULL | 0 | 1 |
| 2 | 3 | NULL | 1 | 2 |
| 3 | 3 | NULL | 2 | 3 |
| 4 | 2 | 2 | 0 | 2 |
| 5 | 2 | 2 | 1 | 3 |
| 6 | 2 | 2 | 2 | 5 |
| 7 | 1 | 1 | 0 | 3 |
| 8 | 1 | 1 | 1 | 5 |
| 9 | 1 | 1 | 2 | 8 |
| 10 | 0 | 0 | 0 | 5 |
| 11 | 0 | 0 | 1 | 8 |
| 12 | 0 | 0 | 2 | 12 |
