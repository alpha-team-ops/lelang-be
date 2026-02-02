// Install: npm install laravel-echo pusher-js

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Initialize Echo with Reverb
window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  wssPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
})

// Usage in React component

export function AuctionDetail({ auctionId }) {
  const [auction, setAuction] = useState(null)

  useEffect(() => {
    // Subscribe to real-time updates
    const channel = window.Echo.channel(`auction.${auctionId}`)

    // Listen for bid placed event
    channel.listen('bid.placed', (data) => {
      console.log('💰 New bid placed:', data)
      // Immediately update UI without polling
      setAuction(prev => ({
        ...prev,
        currentBid: data.bidAmount,
        participantCount: prev.participantCount
      }))
    })

    // Listen for auction updated event
    channel.listen('auction.updated', (data) => {
      console.log('📊 Auction updated:', data)
      setAuction(data)
    })

    // Listen for auction ended event
    channel.listen('auction.ended', (data) => {
      console.log('🏁 Auction ended:', data)
      setAuction(prev => ({
        ...prev,
        status: 'ENDED',
        winner: data.winner
      }))
      // Stop polling, disable bid button, etc.
    })

    return () => {
      // Cleanup on unmount
      window.Echo.leaveChannel(`auction.${auctionId}`)
    }
  }, [auctionId])

  return (
    <div>
      <h1>{auction?.title}</h1>
      <div>Current Bid: Rp {auction?.currentBid}</div>
      <div>{auction?.participantCount} people bidding</div>
    </div>
  )
}
