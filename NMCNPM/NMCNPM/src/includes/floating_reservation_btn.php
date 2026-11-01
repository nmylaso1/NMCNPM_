<?php
// Circular Floating Reservation Button (Compact, Elegant & Non-intrusive)
$curFile = basename($_SERVER['PHP_SELF'] ?? '');
if ($curFile === 'reservation.php' || strpos($_SERVER['REQUEST_URI'] ?? '', '/src/admin/') !== false) {
    return;
}
?>
<style>
    .circular-reservation-fab {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99990;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, #991b1b 0%, #dc2626 50%, #7f1d1d 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: 2px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 24px rgba(185, 28, 28, 0.4), 0 2px 6px rgba(0, 0, 0, 0.12);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
        user-select: none;
    }

    .circular-reservation-fab:hover {
        transform: translateY(-4px) scale(1.1);
        background: linear-gradient(135deg, #b91c1c 0%, #ef4444 50%, #991b1b 100%);
        box-shadow: 0 12px 28px rgba(220, 38, 38, 0.55), 0 0 15px rgba(248, 113, 113, 0.4);
    }

    .circular-reservation-fab:active {
        transform: translateY(1px) scale(0.95);
    }

    /* Soft Aura Ring */
    .fab-aura-ring {
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        border: 2px solid rgba(239, 68, 68, 0.5);
        opacity: 0;
        animation: pulseFabAura 2.4s cubic-bezier(0.24, 0, 0.38, 1) infinite;
        pointer-events: none;
    }

    @keyframes pulseFabAura {
        0% { transform: scale(0.95); opacity: 0.85; }
        50% { opacity: 0.35; }
        100% { transform: scale(1.35); opacity: 0; }
    }

    /* Tooltip on Hover */
    .fab-tooltip {
        position: absolute;
        right: 64px;
        top: 50%;
        transform: translateY(-50%) scale(0.9);
        opacity: 0;
        pointer-events: none;
        background: #1e293b;
        color: #ffffff;
        font-family: 'Quicksand', -apple-system, sans-serif;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
        padding: 5px 12px;
        border-radius: 999px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .fab-tooltip::after {
        content: "";
        position: absolute;
        top: 50%;
        right: -4px;
        transform: translateY(-50%) rotate(45deg);
        width: 8px;
        height: 8px;
        background: #1e293b;
    }

    .circular-reservation-fab:hover .fab-tooltip {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }

    .fab-badge-dot {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #f59e0b;
        border: 2px solid #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
</style>

<a href="reservation.php" class="circular-reservation-fab group" title="Đặt bàn trước">
    <div class="fab-aura-ring"></div>
    <span class="fab-badge-dot"></span>
    
    <div class="group-hover:rotate-12 transition-transform duration-300 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
            <line x1="16" x2="16" y1="2" y2="6"/>
            <line x1="8" x2="8" y1="2" y2="6"/>
            <line x1="3" x2="21" y1="10" y2="10"/>
            <path d="m9 16 2 2 4-4"/>
        </svg>
    </div>

    <span class="fab-tooltip">Đặt bàn ngay ✦</span>
</a>
