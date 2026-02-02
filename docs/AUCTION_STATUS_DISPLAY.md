# Auction Status & Countdown Guide

**For:** Frontend Display Logic | **Purpose:** Know when to show/hide/disable bid button

---

## 📊 Status Flow

```
Timeline:
┌──────────────┬──────────────┬──────────────┐
│   DRAFT      │     LIVE     │    ENDED     │
│ (before      │  (open for   │  (no bidding)│
│  start)      │   bidding)   │              │
└──────────────┴──────────────┴──────────────┘
             start_time    end_time
```

---

## 🎯 Status Rules

### Status Determination Logic
```javascript
function getAuctionStatus(startTime, endTime, now) {
  if (!startTime || !endTime) {
    return 'DRAFT'
  }
  
  if (now < startTime) {
    return 'DRAFT'
  } else if (now <= endTime) {
    return 'LIVE'
  } else {
    return 'ENDED'
  }
}

// Example
now = "2026-01-30T12:00:00Z"
startTime = "2026-01-30T10:00:00Z"
endTime = "2026-01-30T15:00:00Z"
→ Status = 'LIVE'
```

---

## 🔘 Button State by Status

| Status | Show Button | Enabled | Label | Color |
|--------|------------|---------|-------|-------|
| DRAFT | ✅ | ❌ | "Coming Soon" | Gray |
| LIVE | ✅ | ✅ | "Place Bid" | Green |
| ENDING | ✅ | ✅ | "Bid Now!" | Red |
| ENDED | ✅ | ❌ | "Auction Ended" | Gray |

---

## ⏰ Countdown Display

### DRAFT Status
```
Display: "Starts in HH:MM:SS"
Calculate: startTime - now

Example:
startTime = 2026-01-30T14:30:00Z
now = 2026-01-30T13:00:00Z
Display: "Starts in 1:30:00"
```

### LIVE Status
```
Display: "Ends in HH:MM:SS"
Calculate: endTime - now
Update: Every 1000ms

Example:
endTime = 2026-01-30T15:00:00Z
now = 2026-01-30T14:55:00Z
Display: "Ends in 0:05:00"

→ If remaining < 5 minutes: Change color to ORANGE
→ If remaining < 1 minute:  Change color to RED
```

### ENDING Status (Last 5 minutes)
```
Triggered: When endTime - now < 5 minutes
Display: "Final Bids! Ends in MM:SS"
Color: RED (urgent)
Button: "Bid Now!" (emphasized)
```

### ENDED Status
```
Display: "Auction ended at HH:MM (timezone)"
Example: "Auction ended at 15:30 (Asia/Jakarta)"
Button: Disabled
Action: Show winner (if any)
```

---

## 💻 Frontend Implementation

### React Example
```javascript
import { useEffect, useState } from 'react'

export function AuctionCard({ auction }) {
  const [status, setStatus] = useState(auction.status)
  const [countdown, setCountdown] = useState('')
  const [isEnding, setIsEnding] = useState(false)

  useEffect(() => {
    const interval = setInterval(() => {
      const now = new Date()
      const startTime = new Date(auction.startTime)
      const endTime = new Date(auction.endTime)

      // Determine status
      let newStatus
      if (now < startTime) {
        newStatus = 'DRAFT'
      } else if (now <= endTime) {
        newStatus = 'LIVE'
      } else {
        newStatus = 'ENDED'
      }
      setStatus(newStatus)

      // Calculate countdown
      let diff
      if (newStatus === 'DRAFT') {
        diff = startTime - now
      } else if (newStatus === 'LIVE') {
        diff = endTime - now
        setIsEnding(diff < 5 * 60 * 1000) // < 5 minutes
      } else {
        diff = 0
      }

      // Format countdown
      const hours = Math.floor(diff / 3600000)
      const minutes = Math.floor((diff % 3600000) / 60000)
      const seconds = Math.floor((diff % 60000) / 1000)

      const label = newStatus === 'DRAFT' ? 'Starts in' : 'Ends in'
      setCountdown(
        `${label} ${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
      )
    }, 1000)

    return () => clearInterval(interval)
  }, [auction])

  const getButtonState = () => {
    switch (status) {
      case 'DRAFT':
        return { enabled: false, label: 'Coming Soon', color: 'gray' }
      case 'LIVE':
        return {
          enabled: true,
          label: isEnding ? '🔴 Bid Now!' : 'Place Bid',
          color: isEnding ? 'red' : 'green'
        }
      case 'ENDED':
        return { enabled: false, label: 'Auction Ended', color: 'gray' }
      default:
        return { enabled: false, label: 'Loading...', color: 'gray' }
    }
  }

  const buttonState = getButtonState()

  return (
    <div className="auction-card">
      <h3>{auction.title}</h3>
      <div className="bid">Rp {auction.currentBid.toLocaleString()}</div>
      <div className={`countdown ${isEnding ? 'urgent' : ''}`}>
        {countdown}
      </div>
      <div className="participants">{auction.participantCount} bidders</div>
      <button
        disabled={!buttonState.enabled}
        className={buttonState.color}
        onClick={handleBid}
      >
        {buttonState.label}
      </button>
    </div>
  )
}
```

---

### Vue 3 Example
```vue
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({ auction: Object })
const now = ref(new Date())
let timer

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 1000)
})

onUnmounted(() => clearInterval(timer))

const status = computed(() => {
  const startTime = new Date(props.auction.startTime)
  const endTime = new Date(props.auction.endTime)

  if (now.value < startTime) return 'DRAFT'
  if (now.value <= endTime) return 'LIVE'
  return 'ENDED'
})

const countdown = computed(() => {
  const startTime = new Date(props.auction.startTime)
  const endTime = new Date(props.auction.endTime)
  
  let targetTime = status.value === 'DRAFT' ? startTime : endTime
  let diff = targetTime - now.value

  if (diff < 0) diff = 0

  const hours = Math.floor(diff / 3600000)
  const minutes = Math.floor((diff % 3600000) / 60000)
  const seconds = Math.floor((diff % 60000) / 1000)

  const label = status.value === 'DRAFT' ? 'Starts in' : 'Ends in'
  return `${label} ${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
})

const isEnding = computed(() => {
  const endTime = new Date(props.auction.endTime)
  const diff = endTime - now.value
  return status.value === 'LIVE' && diff < 5 * 60 * 1000
})

const buttonState = computed(() => {
  const states = {
    DRAFT: { enabled: false, label: 'Coming Soon', color: 'gray' },
    LIVE: {
      enabled: true,
      label: isEnding.value ? '🔴 Bid Now!' : 'Place Bid',
      color: isEnding.value ? 'red' : 'green'
    },
    ENDED: { enabled: false, label: 'Auction Ended', color: 'gray' }
  }
  return states[status.value] || states.DRAFT
})
</script>

<template>
  <div class="auction-card">
    <h3>{{ auction.title }}</h3>
    <div class="bid">Rp {{ auction.currentBid.toLocaleString() }}</div>
    <div :class="['countdown', { urgent: isEnding }]">
      {{ countdown }}
    </div>
    <div class="participants">{{ auction.participantCount }} bidders</div>
    <button
      :disabled="!buttonState.enabled"
      :class="buttonState.color"
    >
      {{ buttonState.label }}
    </button>
  </div>
</template>
```

---

## 🎨 CSS Styling Guide

```css
/* Countdown timer */
.countdown {
  font-size: 14px;
  color: #666;
  font-weight: 500;
  transition: color 0.3s;
}

.countdown.urgent {
  color: #dc2626;
  font-weight: bold;
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

/* Bid button */
button {
  padding: 10px 20px;
  font-weight: 600;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s;
}

button.green {
  background: #22c55e;
  color: white;
}

button.green:hover:not(:disabled) {
  background: #16a34a;
  transform: scale(1.05);
}

button.red {
  background: #dc2626;
  color: white;
  animation: pulse-red 1s infinite;
}

button.red:hover:not(:disabled) {
  background: #b91c1c;
}

button.gray {
  background: #e5e7eb;
  color: #6b7280;
  cursor: not-allowed;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Status badge */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.live {
  background: #dcfce7;
  color: #166534;
}

.status-badge.ending {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge.draft {
  background: #f3f4f6;
  color: #374151;
}

.status-badge.ended {
  background: #f3f4f6;
  color: #6b7280;
}
```

---

## 📱 Display Examples

### DRAFT Status
```
┌─────────────────────┐
│  Samsung S24 Ultra  │
│                     │
│  Rp 11,000,000      │
│                     │
│  Starts in 2:15:30  │  ← Gray text, updating
│  5 people interested│
│                     │
│  [Coming Soon]      │  ← Gray button, disabled
└─────────────────────┘
```

### LIVE Status (Normal)
```
┌─────────────────────┐
│  Samsung S24 Ultra  │
│                     │
│  Rp 12,500,000      │
│                     │
│  Ends in 0:45:20    │  ← Black text
│  26 people bidding  │
│                     │
│  [Place Bid]        │  ← Green button, enabled
└─────────────────────┘
```

### LIVE Status (ENDING - Last 5 minutes)
```
┌─────────────────────┐
│  Samsung S24 Ultra  │
│                     │
│  Rp 12,750,000      │
│                     │
│  🔴 Ends in 0:03:45 │  ← Red text, blinking
│  32 people bidding  │
│                     │
│  [🔴 Bid Now!]      │  ← Red button, emphasized
└─────────────────────┘
```

### ENDED Status
```
┌─────────────────────┐
│  Samsung S24 Ultra  │
│                     │
│  Final Bid: Rp 13M  │
│                     │
│  Auction ended at   │
│  15:30 (15 mins ago)│
│  32 people bid      │
│                     │
│  [Auction Ended]    │  ← Gray button, disabled
└─────────────────────┘
```

---

## 🔔 Additional Features

### Show Reserve Price Info
```javascript
function shouldShowReserveInfo(auction) {
  return auction.status === 'LIVE' && 
         auction.currentBid < auction.reservePrice
}

// Display
if (shouldShowReserveInfo(auction)) {
  <div className="reserve-not-met">
    ⚠️ Reserve price not met
    <span>Min: Rp {auction.reservePrice.toLocaleString()}</span>
  </div>
}
```

### Show Winning Bid
```javascript
function shouldShowWinner(auction) {
  return auction.status === 'ENDED'
}

// Display
if (shouldShowWinner(auction)) {
  <div className="winner-info">
    Winning bid: Rp {auction.currentBid.toLocaleString()}
    by {auction.currentBidder}
  </div>
}
```

---

## 🧪 Test Cases

```
Test 1: Auction in DRAFT
- start_time = now + 2 hours
- end_time = now + 12 hours
- Expected: Show "Starts in 2:00:00", button disabled

Test 2: Auction LIVE (normal)
- start_time = now - 1 hour
- end_time = now + 4 hours
- Expected: Show "Ends in 4:00:00", button green

Test 3: Auction LIVE (ending - last 5 min)
- start_time = now - 11 hours 55 minutes
- end_time = now + 4 minutes
- Expected: Show red "🔴 Ends in 0:04:00", button red

Test 4: Auction ENDED
- start_time = now - 12 hours
- end_time = now - 1 hour
- Expected: Show "Auction ended", button disabled
```

---

**Last Updated:** January 30, 2026  
**Used in:** Auction Card, Auction Detail Page, Auction List
