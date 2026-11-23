<?php
require_once 'config.php';
$current_lang = get_lang();
?>
<!DOCTYPE html>
<html lang="<?= $current_lang === 'en' ? 'en' : 'ku' ?>" dir="<?= $current_lang === 'en' ? 'ltr' : 'rtl' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#000000">
    <link rel="apple-touch-icon" href="icon-192.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= $current_lang === 'en' ? 'Family Tree - Central System' : 'شجرة العائلة - النظام المركزي' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --focused-border-color: #f6c90e;
            --h-gap: 18px;
            --v-gap: 18px;
            --level-gap: 70px;
            --column-gap: 90px;

            /* ألوان البطاقات - محسّنة */
        
    --male-alive-bg: linear-gradient(135deg, rgba(13, 110, 138, 0.7) 0%, rgba(10, 157, 191, 0.7) 100%);
    --male-deceased-bg: linear-gradient(135deg, rgba(75, 85, 99, 0.7) 0%, rgba(107, 114, 128, 0.7) 100%);
    --female-alive-bg: linear-gradient(135deg, rgba(124, 29, 86, 0.7) 0%, rgba(168, 51, 109, 0.7) 100%);
    --female-deceased-bg: linear-gradient(135deg, rgba(113, 113, 122, 0.7) 0%, rgba(161, 161, 170, 0.7) 100%);
    
    /* أبعاد البطاقات */
    --card-width: 180px; /* تكبير بسيط للحجم */
    --card-height: 75px;

            --spouse-alive-bg: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            --spouse-deceased-bg: linear-gradient(135deg, #737373 0%, #a3a3a3 100%);
            --focused-bg: linear-gradient(135deg, #33518e 0%, #2563eb 100%);

            /* أبعاد البطاقات */
            --card-width: 170px;
            --card-height: 68px;

            /* الخلفية */
            --bg-color:linear-gradient(135deg, #3a5251, #000000, #57565e);

        }

        * { box-sizing: border-box; }

        @keyframes shimmer {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(246,201,14,0.4), 0 4px 15px rgba(0,0,0,0.5); }
            50% { box-shadow: 0 0 30px rgba(246,201,14,0.8), 0 4px 20px rgba(0,0,0,0.6); }
        }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.85) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes spinPulse {
            0% { transform: rotate(0deg); opacity: 1; }
            50% { opacity: 0.6; }
            100% { transform: rotate(360deg); opacity: 1; }
        }
        @keyframes orbFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        html, body {
            width: 100%; height: 100%; margin: 0; padding: 0;
            overflow: hidden;
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-color);
            user-select: none; -webkit-user-select: none;
        }

        /* ========================
           BACKGROUND ORBS
        ======================== */
        .background-orbs {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1; overflow: hidden;
            background: radial-gradient(ellipse at 20% 20%, #1a237e 0%, #0c1445 50%, #0a0e2e 100%);
        }
        .orb {
            position: absolute; border-radius: 50%;
            filter: blur(80px); opacity: 0.35;
            animation: orbFloat ease-in-out infinite;
        }
        .orb1 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, #00ffd3 0%, #007acc 100%);
            top: -200px; left: -200px;
            animation-duration: 8s;
        }
        .orb2 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #bf7cd4 0%, #6d28d9 100%);
            bottom: -200px; right: -150px;
            animation-duration: 11s; animation-delay: -3s;
        }
        .orb3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, #f59e0b 0%, #dc2626 100%);
            top: 40%; left: 40%;
            animation-duration: 15s; animation-delay: -7s;
            opacity: 0.12;
        }

        /* ========================
           TREE CONTAINER & VIEWPORT
        ======================== */
        #tree-container {
            position: relative; width: 100%; height: 100%;
            overflow: hidden; background: transparent;
            touch-action: none;
        }
        #tree-viewport {
            position: absolute; top: 0; left: 0;
            transform-origin: 0 0;
            transition: transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        left 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        top 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        opacity 0.3s ease;
            opacity: 0;
            will-change: transform, left, top;
        }

        /* ========================
           PERSON NODE - REDESIGNED
        ======================== */
        .person-node {
            position: absolute;
            width: var(--card-width);
            height: var(--card-height);
            z-index: 10;
            background: transparent;
            padding: 0;
            transform: translateZ(0);
            transition: filter 0.2s ease;
        }
        .person-node:active { filter: brightness(1.2); }

        .node-visual {
    position: relative;
    width: 100%; height: 100%;
    border-radius: 20px; /* زيادة دائرية الحواف */
    border: 1px solid rgba(255,255,255,0.4);
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); /* ظل أعمق */
    backdrop-filter: blur(8px); /* تأثير ضبابي للخلفية */
    -webkit-backdrop-filter: blur(8px);
    transition: all 0.3s ease;
}
        /* Glass inner shine */
        .node-visual::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255,255,255,0.12) 0%, transparent 100%);
            border-radius: 12px 12px 0 0;
            pointer-events: none;
            z-index: 2;
        }

        /* ---- Gender + Status Colors ---- */
        .person-node.male.alive .node-visual {
            background: var(--male-alive-bg);
            border-color: rgba(99,179,237,0.5);
        }
        .person-node.male.deceased .node-visual {
            background: var(--male-deceased-bg);
            border-color: rgba(156,163,175,0.4);
            opacity: 0.85;
        }
        .person-node.female.alive .node-visual {
            background: var(--female-alive-bg);
            border-color: rgba(246,135,179,0.5);
            border-radius: 16px;
        }
        .person-node.female.alive .node-visual::after {
            content: '';
            position: absolute;
            top: -1px; left: -1px; right: -1px; bottom: -1px;
            border-radius: 16px;
            border: 1px solid rgba(246,135,179,0.2);
            pointer-events: none;
        }
        .person-node.female.deceased .node-visual {
            background: var(--female-deceased-bg);
            border-color: rgba(161,161,170,0.4);
            opacity: 0.85; border-radius: 16px;
        }
        .person-node.spouse.alive .node-visual {
            background: var(--spouse-alive-bg);
            border-color: rgba(52,211,153,0.5);
        }
        .person-node.spouse.deceased .node-visual {
            background: var(--spouse-deceased-bg);
            border-color: rgba(163,163,163,0.4);
            opacity: 0.85;
        }
        .person-node.focused .node-visual {
            background: var(--focused-bg) !important;
            border: 2.5px solid var(--focused-border-color) !important;
            border-radius: 12px !important;
            animation: pulseGlow 2.5s ease-in-out infinite;
        }

        /* ========================
           CARD CONTENT
        ======================== */
        .card-content {
            text-align: center; width: 100%; padding: 0 8px; position: relative; z-index: 3;
        }
        .person-node .name {
            font-size: 14px;
            font-weight: 700;
            color: white;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 4px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.4);
            line-height: 1.3;
        }
        .person-node .sub-name {
            font-size: 10px;
            color: rgba(255,255,255,0.75);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 2px;
            margin-top: 1px;
            font-weight: 400;
        }
        .card-details-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 3px;
        }
        .birth-chip {
            font-size: 10px;
            color: rgba(255,255,255,0.85);
            background: rgba(0,0,0,0.25);
            border-radius: 20px;
            padding: 1px 6px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-weight: 500;
        }
        .birth-chip i { font-size: 8px; opacity: 0.8; }
        .ration-chip {
            font-size: 9px;
            color: rgba(255,255,255,0.7);
            background: rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 1px 5px;
            font-weight: 400;
        }
        .children-count-badge {
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(246,201,14,0.9);
            color: #000;
            font-size: 9px;
            font-weight: 900;
            border-radius: 20px;
            padding: 1px 7px;
            z-index: 30;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            display: none; /* shown via JS */
        }

        /* ========================
           DECEASED RIBBON
        ======================== */
        .ribbon-band {
            position: absolute;
            top: 10px; left: -28px;
            width: 90px; height: 11px;
            background: rgba(0,0,0,0.7);
            transform: rotate(-45deg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
            z-index: 5;
            pointer-events: none;
        }

        /* ========================
           INFO BUTTON
        ======================== */
        .info-btn {
            position: absolute;
            top: -9px; right: -9px;
            width: 22px; height: 22px;
            background: rgba(246,201,14,0.9);
            border: none;
            border-radius: 50%;
            color: #000;
            font-size: 10px;
            font-weight: 900;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
            z-index: 20;
            transition: transform 0.2s, background 0.2s;
        }
        .info-btn:hover, .info-btn:active { transform: scale(1.2); background: #fff; }

        /* ========================
           SVG CONNECTION LINES
        ======================== */
        #lines-svg {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 5; pointer-events: none;
        }
        .connection-line {
            stroke: rgba(255,255,255,0.5);
            stroke-width: 2px;
            fill: none;
            stroke-linecap: round;
        }
        .connection-line.marriage-line {
            stroke: rgba(246,201,14,0.7);
            stroke-width: 2.5px;
        }
        .connection-line.deceased-line {
            stroke-dasharray: 6 4;
            opacity: 0.55;
        }
        .connection-dot {
            fill: rgba(246,201,14,0.9);
        }

        /* ========================
           SCROLL INDICATOR FOR MANY CHILDREN
        ======================== */
        #scroll-hint {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(246,201,14,0.15);
            border: 1px solid rgba(246,201,14,0.4);
            backdrop-filter: blur(10px);
            color: rgba(255,255,255,0.8);
            font-size: 11px;
            padding: 6px 14px;
            border-radius: 20px;
            z-index: 200;
            display: none;
            gap: 6px;
            align-items: center;
            pointer-events: none;
        }
        #scroll-hint i { color: var(--focused-border-color); }

        /* ========================
           CONTROLS
        ======================== */
        #controls {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 100;
            display: flex;
            gap: 8px;
        }
        .btn-ctrl {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            width: 42px; height: 42px;
            border-radius: 10px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .btn-ctrl:hover, .btn-ctrl:active {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
        }

        /* Person counter badge */
        #child-counter {
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 100;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: rgba(255,255,255,0.7);
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 20px;
            display: none;
            align-items: center;
            gap: 5px;
        }
        #child-counter span { color: var(--focused-border-color); font-weight: 900; font-size: 14px; }

        /* ========================
           LOADER
        ======================== */
        #loader {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            color: white;
        }
        .loader-ring {
            width: 48px; height: 48px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: var(--focused-border-color);
            border-radius: 50%;
            animation: spinPulse 0.8s linear infinite;
        }
        #loader p { font-size: 13px; opacity: 0.7; margin: 0; }

        /* ========================
           PROFILE MODAL - PREMIUM REDESIGN
        ======================== */
        #profile-modal-backdrop {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(12px);
            z-index: 999;
            justify-content: center;
            align-items: flex-end; /* Bottom sheet on mobile */
            padding: 0;
        }
      #profile-card {
    position: relative;
    width: 100%;
    max-width: 480px;
    background: rgba(15, 20, 35, 0.85); /* لون داكن شبه شفاف */
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(25px); /* تأثير الزجاج */
    -webkit-backdrop-filter: blur(25px);
    border-radius: 30px 30px 0 0; /* حواف علوية دائرية جداً */
    padding: 0 0 35px 0;
    color: white;
    box-shadow: 0 -10px 40px rgba(0,0,0,0.8);
    animation: fadeSlideIn 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
    overflow: hidden;
    max-height: 90vh;
    overflow-y: auto;
}

        /* Modal drag handle */
        .modal-drag-handle {
            width: 40px; height: 4px;
            background: rgba(255,255,255,0.25);
            border-radius: 2px;
            margin: 12px auto 0;
        }

        .modal-header {
            padding: 18px 22px 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            position: sticky; top: 0;
            background: inherit;
            z-index: 10;
        }
        .modal-avatar {
            width: 52px; height: 52px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .modal-avatar.male { background: linear-gradient(135deg, #0d6e8a, #0a9dbf); }
        .modal-avatar.female { background: linear-gradient(135deg, #7c1d56, #a8336d); }

        .modal-title-block { flex: 1; }
        .modal-name {
            font-size: 18px; font-weight: 900;
            color: var(--focused-border-color);
            margin: 0 0 2px;
            line-height: 1.2;
        }
        .modal-status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; padding: 2px 8px;
            border-radius: 20px; font-weight: 500;
        }
        .modal-status-badge.alive {
            background: rgba(34,197,94,0.15);
            color: #86efac;
            border: 1px solid rgba(34,197,94,0.3);
        }
        .modal-status-badge.deceased {
            background: rgba(156,163,175,0.15);
            color: #d1d5db;
            border: 1px solid rgba(156,163,175,0.3);
        }
        .close-modal {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50%;
            color: #ccc; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .close-modal:hover { background: rgba(255,255,255,0.2); color: white; }

        .modal-body { padding: 14px 22px; }

        .modal-section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            margin: 18px 0 8px;
        }
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
       .modal-info-card {
    background: linear-gradient(145deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 12px 15px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: transform 0.2s ease, background 0.2s ease;
}

.modal-info-card:hover {
    transform: translateY(-2px);
    background: linear-gradient(145deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.05) 100%);
}
        .modal-info-label {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            display: flex; align-items: center; gap: 5px;
            font-weight: 500;
        }
        .modal-info-label i { font-size: 10px; color: var(--focused-border-color); }
        .modal-info-value {
            font-size: 14px;
            font-weight: 700;
            color: white;
            line-height: 1.3;
        }
        .modal-info-value.small { font-size: 12px; font-weight: 500; }

        /* Navigate button inside modal */
        .modal-nav-btn {
            width: 100%;
            margin-top: 16px;
            padding: 12px;
            background: linear-gradient(135deg, rgba(246,201,14,0.15), rgba(246,201,14,0.08));
            border: 1px solid rgba(246,201,14,0.3);
            border-radius: 12px;
            color: var(--focused-border-color);
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.2s ease;
        }
        .modal-nav-btn:hover {
            background: linear-gradient(135deg, rgba(246,201,14,0.3), rgba(246,201,14,0.15));
            transform: translateY(-1px);
        }

        /* ========================
           MINI SEARCH BAR
        ======================== */
        #mini-search {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 400;
            width: min(90%, 340px);
            background: rgba(8,12,28,0.94);
            border: 1px solid rgba(246,201,14,0.35);
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 10px 26px rgba(0,0,0,0.5);
            display: none;
            backdrop-filter: blur(10px);
        }
        #mini-search-input {
            width: 100%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 25px;
            color: white;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            padding: 10px 42px 10px 16px;
            backdrop-filter: blur(10px);
            outline: none;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            display: block;
            position: relative;
        }
        #mini-search-input:focus {
            border-color: rgba(246,201,14,0.5);
            background: rgba(255,255,255,0.12);
        }
        #mini-search-input::placeholder { color: rgba(255,255,255,0.4); }
        #search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: rgba(15, 25, 80, 0.95);
            border-radius: 12px;
            margin-top: 5px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            display: none;
            max-height: 250px;
            overflow-y: auto;
            z-index: 201;
        }
        #search-toggle-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 46px; height: 46px;
            background: rgba(246,201,14,0.15);
            border: 1px solid rgba(246,201,14,0.4);
            border-radius: 50%;
            color: var(--focused-border-color);
            font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            backdrop-filter: blur(10px);
            z-index: 201;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        #search-toggle-btn:hover {
            background: rgba(246,201,14,0.3);
            transform: scale(1.05);
        }

        /* ========================
           ZOOM BUTTONS
        ======================== */
        #zoom-controls {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 200;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .zoom-btn {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
            font-size: 18px;
            font-weight: 300;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.15s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .zoom-btn:hover { background: rgba(255,255,255,0.18); }

        /* ========================
           RESPONSIVE - MOBILE
        ======================== */
        @media (max-width: 768px) {
            :root {
                --card-width: 140px;
                --card-height: 62px;
                --column-gap: 75px;
                --h-gap: 12px;
                --v-gap: 14px;
                --level-gap: 60px;
            }
            .person-node .name { font-size: 13px; }
            .birth-chip { font-size: 9px; padding: 1px 5px; }
            #profile-card { border-radius: 20px 20px 0 0; }
        }
        @media (min-width: 769px) {
            #profile-modal-backdrop {
                align-items: center;
                padding: 20px;
            }
            #profile-card {
                border-radius: 24px;
                border-bottom: 1px solid rgba(255,255,255,0.12);
                max-width: 420px;
                width: 100%;
            }
        }
        /* ========================
   تحديثات المودال (الصورة والملاحظات)
======================== */
.modal-profile-img-container {
    display: flex;
    justify-content: center;
    margin-top: -40px; /* لرفع الصورة للأعلى قليلاً */
    margin-bottom: 15px;
}
.modal-profile-img {
    width: 200px;
    height: 200px;
    border-radius: 25%;
    object-fit: cover;
    border: 4px solid var(--focused-border-color);
    background: #2a2a2a;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
    z-index: 20;
}

.modal-notes-section {
    grid-column: 1 / -1;
    background: rgba(246, 201, 14, 0.1);
    border-left: 3px solid var(--focused-border-color);
    padding: 10px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
    color: #eee;
}


#search-container {
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 200;
    width: 300px;
}
#search-input {
    width: 100%;
    padding: 10px 15px;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(0,0,0,0.5);
    color: white;
    backdrop-filter: blur(10px);
    outline: none;
    font-family: 'Tajawal', sans-serif;
}
#search-results {
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: rgba(15, 25, 80, 0.95);
    border-radius: 12px;
    margin-top: 5px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
    display: none;
    max-height: 300px;
    overflow-y: auto;
}
.search-item {
    padding: 12px 15px;
    color: white;
    font-size: 13px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    transition: background 0.2s;
}
.search-item:hover {
    background: rgba(246, 201, 14, 0.2);
}
.search-item:last-child {
    border-bottom: none;
}

        /* ========================
           LTR DIRECTION OVERRIDES
        ======================== */
        html[dir="ltr"] #controls {
            right: auto;
            left: 16px;
        }
        html[dir="ltr"] #child-counter {
            left: auto;
            right: 16px;
        }
        html[dir="ltr"] #search-toggle-btn {
            right: auto;
            left: 20px;
        }
        html[dir="ltr"] .info-btn {
            right: auto;
            left: -9px;
        }
        html[dir="ltr"] .ribbon-band {
            left: auto;
            right: -28px;
            transform: rotate(45deg);
        }
        html[dir="ltr"] .modal-notes-section {
            border-left: none;
            border-right: 3px solid var(--focused-border-color);
        }
    </style>
</head>
<body>

    <div class="background-orbs">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
        <div class="orb3 orb"></div>
    </div>

    <div id="loader">
        <div class="loader-ring"></div>
        <p><?= $current_lang === 'en' ? 'Loading...' : 'جار بارکردن...' ?></p>
    </div>

    <div id="controls">
        <a href="relationship.php" class="btn-ctrl" title="<?= $current_lang === 'en' ? 'Family Connection' : 'پەیوەندییا خزماتیێ' ?>" aria-label="<?= $current_lang === 'en' ? 'Family Connection' : 'پەیوەندییا خزماتیێ' ?>"><i class="fas fa-route"></i></a>
        <a href="index.php" class="btn-ctrl" title="<?= $current_lang === 'en' ? 'Home' : 'الرئيسية' ?>"><i class="fas fa-home"></i></a>
        <a href="family_list.php" class="btn-ctrl" title="<?= $current_lang === 'en' ? 'List' : 'القائمة' ?>"><i class="fas fa-list"></i></a>
        <a href="tree.php" class="btn-ctrl" title="<?= $current_lang === 'en' ? 'Reload' : 'إعادة تحميل' ?>"><i class="fas fa-sync-alt"></i></a>
        <button id="fullscreen-btn" class="btn-ctrl" title="<?= $current_lang === 'en' ? 'Fullscreen' : 'شاشة كاملة' ?>"><i class="fas fa-expand"></i></button>
    </div>

    <!-- Child count indicator (shown when many children) -->
    <div id="child-counter"><i class="fas fa-child" style="color:var(--focused-border-color)"></i> <span id="child-count-num">0</span> <?= $current_lang === 'en' ? 'children' : 'کوڕ و کچ' ?></div>

    <!-- Scroll hint -->
    <div id="scroll-hint"><i class="fas fa-arrows-alt"></i> <?= $current_lang === 'en' ? 'Drag to see all' : 'بکێشە بۆ بینینی هەمووان' ?></div>

    <div id="tree-container">
        <div id="tree-viewport"></div>
    </div>

    <!-- Zoom controls -->
    <div id="zoom-controls">
        <button class="zoom-btn" id="zoom-in-btn"><i class="fas fa-plus"></i></button>
        <button class="zoom-btn" id="zoom-out-btn"><i class="fas fa-minus"></i></button>
        <button class="zoom-btn" id="zoom-reset-btn" style="font-size:12px;"><i class="fas fa-compress-arrows-alt"></i></button>
    </div>

    <!-- Search -->
    <button id="search-toggle-btn"><i class="fas fa-search"></i></button>
    <div id="mini-search">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:10px;">
            <button id="fill-search-btn" class="btn-ctrl" style="width:auto; height:auto; padding:6px 10px; border-radius:10px; font-size:12px;">اكتب في المدخل</button>
            <button id="mini-search-close" class="close-modal" style="width:28px; height:28px; font-size:16px;">×</button>
        </div>
        <input type="text" id="mini-search-input" placeholder="<?= $current_lang === 'en' ? 'Search in family...' : 'گەڕان بە ناو بنەمالەدا...' ?>" autocomplete="off">
        <div id="search-results"></div>
    </div>

    <!-- Profile Modal -->
    <div id="profile-modal-backdrop">
        <div id="profile-card">
            <div class="modal-drag-handle"></div>
            <div class="modal-header">
                <div class="modal-avatar" id="modal-avatar"><i class="fas fa-user"></i></div>
                <div class="modal-title-block">
                    <div class="modal-name" id="modal-name">—</div>
                    <div class="modal-status-badge" id="modal-status-badge"></div>
                </div>
                <span class="close-modal" id="close-modal-btn">&times;</span>
            </div>
            <div class="modal-body" id="profile-content"></div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const LANG = '<?= $current_lang ?>';
    const container = document.getElementById('tree-container');
    const viewport = document.getElementById('tree-viewport');
    const loader = document.getElementById('loader');
    let nodeMap = new Map();
    let currentData = null;
    let currentFocusedId = null;

    // Current transform state
    let state = { scale: 1, left: 0, top: 0 };
    // For smooth drag/pinch
    let drag = { active: false, startX: 0, startY: 0, lastLeft: 0, lastTop: 0 };
    let pinch = { active: false, startDist: 0, startScale: 1 };

    // ==========================================
    // 1. LAYOUT ALGORITHM — FIXED SPOUSE POSITIONING
    // ==========================================
    function calculateLayout(data, focusedNode) {
        const styles = getComputedStyle(document.documentElement);
        const cardW = parseInt(styles.getPropertyValue('--card-width'));
        const cardH = parseInt(styles.getPropertyValue('--card-height'));
        const hGap = parseInt(styles.getPropertyValue('--h-gap'));
        const vGap = parseInt(styles.getPropertyValue('--v-gap'));
        const levelGap = parseInt(styles.getPropertyValue('--level-gap'));
        const columnGap = parseInt(styles.getPropertyValue('--column-gap'));

        // Focused person at center
        focusedNode.x = 0;
        focusedNode.y = 0;

        // ---- SPOUSE DISTRIBUTION (FIXED LOGIC) ----
        const spouses = (focusedNode.pids || []).map(pid => nodeMap.get(pid)).filter(Boolean);
        spouses.forEach(s => { if (!s.tags.includes('spouse')) s.tags.push('spouse'); });

        const spouseCount = spouses.length;

        if (spouseCount === 1) {
            // 1 spouse: to the RIGHT only
            spouses[0].x = cardW + hGap;
            spouses[0].y = 0;
        } else if (spouseCount === 2) {
            // 2 spouses: one left, one right
            spouses[0].x = -(cardW + hGap);
            spouses[0].y = 0;
            spouses[1].x = (cardW + hGap);
            spouses[1].y = 0;
        } else if (spouseCount === 3) {
            // 3 spouses: 2 on right, 1 on left
            spouses[0].x = -(cardW + hGap);        // left
            spouses[0].y = 0;
            spouses[1].x = (cardW + hGap);          // right #1
            spouses[1].y = 0;
            spouses[2].x = (cardW + hGap) * 2 + hGap; // right #2 — add extra hGap
            spouses[2].y = 0;
        } else {
            // 4+ spouses: distribute symmetrically
            const half = Math.floor(spouseCount / 2);
            const leftSpouses = spouses.slice(0, half);
            const rightSpouses = spouses.slice(half);
            [...leftSpouses].reverse().forEach((s, i) => {
                s.x = -((i + 1) * (cardW + hGap));
                s.y = 0;
            });
            rightSpouses.forEach((s, i) => {
                s.x = ((i + 1) * (cardW + hGap));
                s.y = 0;
            });
        }

        // ---- CHILDREN DISTRIBUTION ----
        let children = data.nodes.filter(n =>
            n.fid === focusedNode.id || n.mid === focusedNode.id
        );
        children.sort((a, b) => {
            const yA = a.birth_year ? parseInt(a.birth_year) : 9999;
            const yB = b.birth_year ? parseInt(b.birth_year) : 9999;
            return yA - yB;
        });

        if (children.length > 0) {
            const startY = cardH + levelGap;

            if (children.length <= 4) {
                // Simple horizontal row
                const totalW = children.length * cardW + (children.length - 1) * hGap;
                let startX = -(totalW / 2) + (cardW / 2);
                children.forEach((child, i) => {
                    child.x = startX + i * (cardW + hGap);
                    child.y = startY;
                });
            } else {
                // TWO COLUMNS with scroll — right and left
                const rightColX = (columnGap / 2) + (cardW / 2);
                const leftColX = -(columnGap / 2) - (cardW / 2);
                const mid = Math.ceil(children.length / 2);
                const rightGroup = children.slice(0, mid);
                const leftGroup = children.slice(mid);

                rightGroup.forEach((c, i) => {
                    c.x = rightColX;
                    c.y = startY + i * (cardH + vGap);
                    c.col = 'right';
                });
                leftGroup.forEach((c, i) => {
                    c.x = leftColX;
                    c.y = startY + i * (cardH + vGap);
                    c.col = 'left';
                });
            }
        }

        return { cardW, cardH, levelGap, hGap, vGap };
    }

    // ==========================================
    // 2. DRAW CONNECTION LINES
    // ==========================================
    function drawLines(nodes, svgLayer, dims) {
        const { cardW, cardH, levelGap } = dims;
        const focusedNode = nodes.find(n => n.tags.includes('focused'));
        if (!focusedNode) return;

        function createPath(d, cls) {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            path.setAttribute('class', 'connection-line ' + (cls || ''));
            svgLayer.appendChild(path);
        }
        function createDot(cx, cy, r) {
            const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            c.setAttribute('cx', cx); c.setAttribute('cy', cy);
            c.setAttribute('r', r || 4);
            c.setAttribute('class', 'connection-dot');
            svgLayer.appendChild(c);
        }

        // ---- Spouse lines ----
        const spouses = (focusedNode.pids || [])
            .map(pid => nodeMap.get(pid))
            .filter(n => n && typeof n.x !== 'undefined');
        const parentGroup = [focusedNode, ...spouses].sort((a, b) => a.x - b.x);

        for (let i = 0; i < parentGroup.length - 1; i++) {
            const curr = parentGroup[i];
            const next = parentGroup[i + 1];
            const startX = curr.x + cardW / 2 + 1;
            const endX = next.x - cardW / 2 - 1;
            if (endX > startX) {
                const isD = curr.tags.includes('deceased') || next.tags.includes('deceased');
                createPath(`M ${startX} ${curr.y} L ${endX} ${curr.y}`,
                    'marriage-line' + (isD ? ' deceased-line' : ''));
                // Heart dot at midpoint
                createDot((startX + endX) / 2, curr.y, 3);
            }
        }

        // ---- Children lines ----
        const children = nodes.filter(n =>
            (n.fid === focusedNode.id || n.mid === focusedNode.id) && typeof n.x !== 'undefined'
        );
        if (children.length === 0) return;

        const stemStartY = focusedNode.y + cardH / 2;
        const branchY = stemStartY + levelGap / 2;

        createPath(`M ${focusedNode.x} ${stemStartY} V ${branchY}`);

        if (children.length <= 4) {
            // Horizontal branch
            const minCX = Math.min(...children.map(c => c.x));
            const maxCX = Math.max(...children.map(c => c.x));
            if (minCX !== maxCX) createPath(`M ${minCX} ${branchY} H ${maxCX}`);
            children.forEach(c => {
                createPath(`M ${c.x} ${branchY} V ${c.y - cardH / 2}`,
                    c.tags.includes('deceased') ? 'deceased-line' : '');
            });
        } else {
            // Two-column vertical spine
            const spineR = focusedNode.x + 28;
            const spineL = focusedNode.x - 28;
            createPath(`M ${spineL} ${branchY} H ${spineR}`);

            ['right', 'left'].forEach(side => {
                const sideKids = children.filter(c => c.col === side);
                if (sideKids.length === 0) return;
                const spineX = side === 'right' ? spineR : spineL;
                const maxY = Math.max(...sideKids.map(c => c.y));
                createPath(`M ${spineX} ${branchY} V ${maxY}`);
                sideKids.forEach(c => {
                    const targetX = side === 'right'
                        ? c.x - cardW / 2 - 1
                        : c.x + cardW / 2 + 1;
                    createPath(`M ${spineX} ${c.y} L ${targetX} ${c.y}`,
                        c.tags.includes('deceased') ? 'deceased-line' : '');
                });
            });
        }
    }

    // ==========================================
    // 3. RENDER TREE
    // ==========================================
    function renderTree(data) {
        currentData = data;
        viewport.innerHTML = '';
        nodeMap.clear();
        data.nodes.forEach(n => {
            if (!n.tags) n.tags = [];
            if (n.is_deceased || n.status === 'Deceased') {
                if (!n.tags.includes('deceased')) n.tags.push('deceased');
            }
            nodeMap.set(n.id, n);
        });

        const focusedNode = data.nodes.find(n => n.tags.includes('focused'));
        if (!focusedNode) { alert('خطأ: لا يوجد شخص محدد'); return; }
        currentFocusedId = focusedNode.id;

        const dims = calculateLayout(data, focusedNode);

        // Count children for UI indicators
        const childrenCount = data.nodes.filter(n =>
            n.fid === focusedNode.id || n.mid === focusedNode.id
        ).length;

        // Show child counter if > 5
        const counterEl = document.getElementById('child-counter');
        const scrollHint = document.getElementById('scroll-hint');
        if (childrenCount > 5) {
            document.getElementById('child-count-num').textContent = childrenCount;
            counterEl.style.display = 'flex';
            scrollHint.style.display = 'flex';
            setTimeout(() => { scrollHint.style.display = 'none'; }, 3000);
        } else {
            counterEl.style.display = 'none';
            scrollHint.style.display = 'none';
        }

        // ---- Compute bounding box ----
        let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
        data.nodes.forEach(node => {
            if (typeof node.x === 'number') {
                minX = Math.min(minX, node.x - dims.cardW / 2);
                maxX = Math.max(maxX, node.x + dims.cardW / 2);
                minY = Math.min(minY, node.y - dims.cardH / 2);
                maxY = Math.max(maxY, node.y + dims.cardH / 2);
            }
        });

        const padding = 60;
        const treeWidth = (maxX - minX) + padding * 2;
        const treeHeight = (maxY - minY) + padding * 2;

        // SVG layer
        const svgLayer = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svgLayer.setAttribute('id', 'lines-svg');
        svgLayer.setAttribute('viewBox', `${minX - padding} ${minY - padding} ${treeWidth} ${treeHeight}`);
        svgLayer.style.width = '100%';
        svgLayer.style.height = '100%';
        viewport.appendChild(svgLayer);

        viewport.style.width = `${treeWidth}px`;
        viewport.style.height = `${treeHeight}px`;

        // ---- Render nodes ----
        data.nodes.forEach(node => {
            if (typeof node.x === 'undefined') return;

            const el = document.createElement('div');
            let classes = ['person-node'];
            classes.push(node.gender === 'Male' ? 'male' : 'female');
            classes.push(node.tags.includes('deceased') ? 'deceased' : 'alive');
            if (node.tags.includes('focused')) classes.push('focused');
            if (node.tags.includes('spouse') && !node.tags.includes('focused')) classes.push('spouse');
            el.className = classes.join(' ');

            const leftPos = node.x - dims.cardW / 2 - (minX - padding);
            const topPos = node.y - dims.cardH / 2 - (minY - padding);
            el.style.left = `${leftPos}px`;
            el.style.top = `${topPos}px`;

            const ribbon = node.tags.includes('deceased') ? '<div class="ribbon-band"></div>' : '';

            // Build name parts: first name + rest as sub-name
            const nameParts = (node.name || '').trim().split(' ');
            const firstName = nameParts[0] || '';
            const restName = nameParts.slice(1).join(' ');

            // Birth year chip
            const birthYear = node.birth_year || '';
            const birthChip = birthYear
                ? `<div class="birth-chip"><i class="fas fa-calendar-alt"></i>${birthYear}</div>`
                : '';

            // Ration number (short)
            const rationNum = node.ration_number
                ? `<div class="ration-chip">#${String(node.ration_number).slice(-4)}</div>`
                : '';

            // Children count badge (shown on focused node if has children)
            const childrenOfNode = data.nodes.filter(n => n.fid === node.id).length;
            const childBadge = (childrenOfNode > 0 && node.tags.includes('focused'))
                ? `<div class="children-count-badge" style="display:block">${childrenOfNode} <i class="fas fa-child" style="font-size:8px"></i></div>`
                : '';

            el.innerHTML = `
                <div class="node-visual">
                    ${ribbon}
                    <div class="card-content">
                        <div class="name">${firstName}</div>
                        ${restName ? `<div class="sub-name">${restName}</div>` : ''}
                        <div class="card-details-row">
                            ${birthChip}
                            ${rationNum}
                        </div>
                    </div>
                </div>
                <div class="info-btn"><i class="fas fa-info"></i></div>
                ${childBadge}
            `;

            el.querySelector('.info-btn').addEventListener('click', e => {
                e.stopPropagation();
                showProfileModal(node.id);
            });
            el.addEventListener('click', () => {
                if (node.id === focusedNode.id && data.parent_id) loadFamily(data.parent_id);
                else if (node.tags.includes('spouse')) loadFamily(node.fid || focusedNode.id);
                else loadFamily(node.id);
            });

            viewport.appendChild(el);
        });

        drawLines(data.nodes, svgLayer, dims);

        // Anchor = focused person position within viewport
        const anchorX = (0 - minX) + padding;
        fitTreeToScreen(treeWidth, treeHeight, anchorX);

        loader.style.display = 'none';
        viewport.style.opacity = '1';
    }

    // ==========================================
    // 4. FIT TO SCREEN — RESPONSIVE CENTERING
    // ==========================================
    function fitTreeToScreen(treeW, treeH, anchorX) {
        const cW = container.clientWidth;
        const cH = container.clientHeight;

        let scale = Math.min(cW / treeW, cH / treeH, 1.2);
        if (scale > 1) scale = 1;
        if (scale < 0.3) scale = 0.3;

        // Center the focused person horizontally
        let newLeft = cW / 2 - anchorX * scale;
        let newTop = 50;
        if (treeH * scale < cH) {
            newTop = (cH - treeH * scale) / 2;
        }

        state = { scale, left: newLeft, top: newTop };
        applyTransform(true);
    }

    function applyTransform(animated) {
        if (!animated) {
            viewport.style.transition = 'none';
        } else {
            viewport.style.transition = 'transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94), left 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94), top 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        }
        viewport.style.transform = `scale(${state.scale})`;
        viewport.style.left = `${state.left}px`;
        viewport.style.top = `${state.top}px`;
    }

    // ==========================================
    // 5. DRAG & PINCH-TO-ZOOM (TOUCH + MOUSE)
    // ==========================================
    function getDistance(t1, t2) {
        return Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
    }
    function getMidpoint(t1, t2) {
        return { x: (t1.clientX + t2.clientX) / 2, y: (t1.clientY + t2.clientY) / 2 };
    }

    container.addEventListener('touchstart', e => {
        if (e.touches.length === 2) {
            pinch.active = true;
            pinch.startDist = getDistance(e.touches[0], e.touches[1]);
            pinch.startScale = state.scale;
            pinch.mid = getMidpoint(e.touches[0], e.touches[1]);
            drag.active = false;
        } else if (e.touches.length === 1) {
            drag.active = true;
            drag.startX = e.touches[0].clientX;
            drag.startY = e.touches[0].clientY;
            drag.lastLeft = state.left;
            drag.lastTop = state.top;
        }
    }, { passive: true });

    container.addEventListener('touchmove', e => {
        if (pinch.active && e.touches.length === 2) {
            e.preventDefault();
            const dist = getDistance(e.touches[0], e.touches[1]);
            const ratio = dist / pinch.startDist;
            const newScale = Math.max(0.25, Math.min(2.5, pinch.startScale * ratio));
            const mid = getMidpoint(e.touches[0], e.touches[1]);
            // Scale around pinch midpoint
            state.left = mid.x - (mid.x - state.left) * (newScale / state.scale);
            state.top = mid.y - (mid.y - state.top) * (newScale / state.scale);
            state.scale = newScale;
            applyTransform(false);
        } else if (drag.active && e.touches.length === 1) {
            const dx = e.touches[0].clientX - drag.startX;
            const dy = e.touches[0].clientY - drag.startY;
            state.left = drag.lastLeft + dx;
            state.top = drag.lastTop + dy;
            applyTransform(false);
        }
    }, { passive: false });

    container.addEventListener('touchend', e => {
        if (e.touches.length < 2) pinch.active = false;
        if (e.touches.length === 0) drag.active = false;
    });

    // Mouse drag
    container.addEventListener('mousedown', e => {
        if (e.button !== 0) return;
        drag.active = true;
        drag.startX = e.clientX;
        drag.startY = e.clientY;
        drag.lastLeft = state.left;
        drag.lastTop = state.top;
        container.style.cursor = 'grabbing';
    });
    window.addEventListener('mousemove', e => {
        if (!drag.active) return;
        state.left = drag.lastLeft + (e.clientX - drag.startX);
        state.top = drag.lastTop + (e.clientY - drag.startY);
        applyTransform(false);
    });
    window.addEventListener('mouseup', () => {
        drag.active = false;
        container.style.cursor = '';
    });

    // Mouse wheel zoom
    container.addEventListener('wheel', e => {
        e.preventDefault();
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        const newScale = Math.max(0.25, Math.min(2.5, state.scale * delta));
        // Zoom around cursor position
        state.left = e.clientX - (e.clientX - state.left) * (newScale / state.scale);
        state.top = e.clientY - (e.clientY - state.top) * (newScale / state.scale);
        state.scale = newScale;
        applyTransform(false);
    }, { passive: false });

    // Zoom buttons
    document.getElementById('zoom-in-btn').onclick = () => {
        const newScale = Math.min(2.5, state.scale * 1.2);
        const cW = container.clientWidth, cH = container.clientHeight;
        state.left = cW / 2 - (cW / 2 - state.left) * (newScale / state.scale);
        state.top = cH / 2 - (cH / 2 - state.top) * (newScale / state.scale);
        state.scale = newScale;
        applyTransform(true);
    };
    document.getElementById('zoom-out-btn').onclick = () => {
        const newScale = Math.max(0.25, state.scale * 0.8);
        const cW = container.clientWidth, cH = container.clientHeight;
        state.left = cW / 2 - (cW / 2 - state.left) * (newScale / state.scale);
        state.top = cH / 2 - (cH / 2 - state.top) * (newScale / state.scale);
        state.scale = newScale;
        applyTransform(true);
    };
    document.getElementById('zoom-reset-btn').onclick = () => {
        if (currentData) renderTree(currentData);
    };

    // ==========================================
    // 6. PROFILE MODAL — PREMIUM
    // ==========================================
    function showProfileModal(id) {
    const node = nodeMap.get(id);
    if (!node) return;
    
    const modal = document.getElementById('profile-modal-backdrop');
    const content = document.getElementById('profile-content');
    
    // إعداد الصورة
    const imageHtml = node.image_url && node.image_url.trim() !== '' 
        ? `<img src="${node.image_url}" class="modal-profile-img" alt="${node.name}">`
        : `<div class="modal-profile-img" style="display:flex;align-items:center;justify-content:center;font-size:40px;"><i class="fas ${node.gender === 'Male' ? 'fa-mars' : 'fa-venus'}"></i></div>`;

    // إعداد الملاحظات
    const notesLabel = LANG === 'en' ? 'Notes:' : 'ملاحظات:';
    const notesHtml = node.notes && node.notes.trim() !== ''
        ? `<div class="modal-notes-section">
             <strong><i class="fas fa-sticky-note"></i> ${notesLabel}</strong><br>
             ${node.notes}
           </div>`
        : '';

    const isMale = node.gender === 'Male';
    const genderText = LANG === 'en' ? (isMale ? 'Male' : 'Female') : (isMale ? 'ذكر' : 'أنثى');
    const statusText = node.status || (node.tags.includes('deceased') 
        ? (LANG === 'en' ? 'Deceased' : 'متوفي') 
        : (LANG === 'en' ? 'Alive' : 'على قيد الحياة'));
    const detailsTitle = LANG === 'en' ? 'Additional Details' : 'تفاصيل إضافية';
    const birthLabel = LANG === 'en' ? 'Birth Year' : 'سنة الميلاد';
    const genderLabel = LANG === 'en' ? 'Gender' : 'الجنس';
    const notAvailable = LANG === 'en' ? 'N/A' : 'غير متوفر';
    const viewTreeBtn = LANG === 'en' ? 'View Family Tree' : 'عرض شجرة العائلة الخاصة به/بها';

    content.innerHTML = `
        <div class="modal-profile-img-container">
            ${imageHtml}
        </div>
        <div style="text-align:center; margin-bottom: 15px;">
            <h2 style="margin:0; color:var(--focused-border-color);">${node.name}</h2>
            <span style="font-size:12px; color:#aaa;">${statusText}</span>
        </div>
        
        <div class="modal-section-title">${detailsTitle}</div>
        <div class="modal-grid">
            <div class="modal-info-card">
                <div class="modal-info-label"><i class="fas fa-calendar-alt"></i> ${birthLabel}</div>
                <div class="modal-info-value small">${node.birth_year || notAvailable}</div>
            </div>
            <div class="modal-info-card">
                <div class="modal-info-label"><i class="fas fa-${isMale ? 'mars' : 'venus'}"></i> ${genderLabel}</div>
                <div class="modal-info-value">${genderText}</div>
            </div>
            ${notesHtml}
        </div>
        <button class="modal-nav-btn" id="modal-goto-btn" style="width:100%; padding:12px; background:var(--focused-border-color); color:#000; border:none; border-radius:8px; margin-top:15px; font-weight:bold; cursor:pointer;">
            <i class="fas fa-sitemap"></i> ${viewTreeBtn}
        </button>
    `;

    document.getElementById('modal-goto-btn').onclick = () => {
        modal.style.display = 'none';
        loadFamily(node.id);
    };

    modal.style.display = 'flex';
}

    document.getElementById('close-modal-btn').onclick = () => {
        document.getElementById('profile-modal-backdrop').style.display = 'none';
    };
    document.getElementById('profile-modal-backdrop').addEventListener('click', e => {
        if (e.target === document.getElementById('profile-modal-backdrop')) {
            e.target.style.display = 'none';
        }
    });

    // ==========================================
    // 7. SEARCH WITH THREE-PART NAMES
    // ==========================================
    const searchBtn = document.getElementById('search-toggle-btn');
    const miniSearchPanel = document.getElementById('mini-search');
    const searchInput = document.getElementById('mini-search-input');
    const searchResults = document.getElementById('search-results');
    const fillSearchBtn = document.getElementById('fill-search-btn');
    const miniSearchCloseBtn = document.getElementById('mini-search-close');
    let searchOpen = false;
    let allPeopleData = []; // Store all people data for search

    // Function to get three-part name (person + father + grandfather)
    function getThreePartName(personId, peopleMap) {
        const person = peopleMap.get(personId);
        if (!person) return '';

        let name = person.name || person.full_name || '';
        let fatherName = '';
        let grandfatherName = '';

        if (person.father_id) {
            const father = peopleMap.get(person.father_id);
            if (father) {
                fatherName = father.name || father.full_name || '';
                if (father.father_id) {
                    const grandfather = peopleMap.get(father.father_id);
                    if (grandfather) {
                        grandfatherName = grandfather.name || grandfather.full_name || '';
                    }
                }
            }
        }

        const parts = [name, fatherName, grandfatherName].filter(n => n.trim());
        return parts.join(' ');
    }

    // Function to load all people data for search
    function loadAllPeopleData() {
        if (allPeopleData.length > 0) return Promise.resolve(allPeopleData);

        return fetch('get_data.php?all=1')
            .then(r => r.json())
            .then(data => {
                if (data.nodes) {
                    allPeopleData = data.nodes;
                    return allPeopleData;
                }
                return [];
            })
            .catch(e => {
                console.error('Error loading people data:', e);
                return [];
            });
    }

    // Function to perform search
    function performSearch(query) {
        if (!query.trim()) {
            searchResults.style.display = 'none';
            return;
        }

        loadAllPeopleData().then(people => {
            const queryLower = query.toLowerCase();
            const matches = [];

            // Create a map for quick lookup
            const peopleMap = new Map();
            people.forEach(person => {
                peopleMap.set(person.id, person);
            });

            // Search through all people
            people.forEach(person => {
                const threePartName = getThreePartName(person.id, peopleMap).toLowerCase();
                const singleName = (person.name || '').toLowerCase();

                if (threePartName.includes(queryLower) || singleName.includes(queryLower)) {
                    matches.push({
                        id: person.id,
                        name: person.name || '',
                        threePartName: getThreePartName(person.id, peopleMap),
                        gender: person.gender,
                        status: person.status
                    });
                }
            });

            // Limit results to 10
            const topMatches = matches.slice(0, 10);

            if (topMatches.length > 0) {
                displaySearchResults(topMatches);
            } else {
                searchResults.style.display = 'none';
            }
        });
    }

    // Function to display search results
    function displaySearchResults(results) {
        searchResults.innerHTML = '';

        results.forEach(result => {
            const item = document.createElement('div');
            item.className = 'search-item';
            item.innerHTML = `
                <div style="font-weight: bold; color: var(--focused-border-color);">
                    ${result.name}
                </div>
                <div style="font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 2px;">
                    ${result.threePartName}
                </div>
            `;

            item.addEventListener('click', () => {
                // Navigate to the selected person
                searchOpen = false;
                miniSearchPanel.style.display = 'none';
                searchInput.value = '';
                searchBtn.innerHTML = '<i class="fas fa-search"></i>';
                searchResults.style.display = 'none';
                document.querySelectorAll('.person-node').forEach(el => el.style.filter = '');
                loadFamily(result.id);
            });

            searchResults.appendChild(item);
        });

        searchResults.style.display = 'block';
    }

    searchBtn.onclick = () => {
        searchOpen = !searchOpen;
        miniSearchPanel.style.display = searchOpen ? 'block' : 'none';
        if (!searchOpen) {
            searchInput.value = '';
            searchResults.style.display = 'none';
            document.querySelectorAll('.person-node').forEach(el => el.style.filter = '');
        }

        if (searchOpen) {
            setTimeout(() => searchInput.focus(), 50);
            searchBtn.innerHTML = '<i class="fas fa-times"></i>';
        } else {
            searchBtn.innerHTML = '<i class="fas fa-search"></i>';
        }
    };

    // زر تعبئة نص سريع في حقل البحث
    fillSearchBtn.onclick = () => {
        const sampleText = 'أحمد';
        searchInput.value = sampleText;
        performSearch(sampleText);
    };

    // زر إغلاق لوحة البحث الوسطية
    miniSearchCloseBtn.onclick = () => {
        searchOpen = false;
        miniSearchPanel.style.display = 'none';
        searchBtn.innerHTML = '<i class="fas fa-search"></i>';
        searchResults.style.display = 'none';
    };

    // اغلاق اللوحة باستخدام زر مودال الخروج العام
    document.getElementById('close-modal-btn').onclick = () => {
        document.getElementById('profile-modal-backdrop').style.display = 'none';
        searchOpen = false;
        miniSearchPanel.style.display = 'none';
        searchBtn.innerHTML = '<i class="fas fa-search"></i>';
        searchResults.style.display = 'none';
    };

    // Search input event listeners
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        const query = searchInput.value.trim();

        if (query.length >= 2) {
            searchTimeout = setTimeout(() => performSearch(query), 300);
        } else {
            searchResults.style.display = 'none';
        }

        // Also highlight current tree nodes for immediate feedback
        if (currentData) {
            document.querySelectorAll('.person-node').forEach(el => {
                const name = el.querySelector('.name')?.textContent.toLowerCase() || '';
                const subname = el.querySelector('.sub-name')?.textContent.toLowerCase() || '';
                if (name.includes(query.toLowerCase()) || subname.includes(query.toLowerCase())) {
                    el.style.filter = 'brightness(1.4) drop-shadow(0 0 12px rgba(246,201,14,0.9))';
                } else {
                    el.style.filter = 'brightness(0.45)';
                }
            });
        }
    });

    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            searchOpen = false;
            miniSearchPanel.style.display = 'none';
            searchInput.value = '';
            searchBtn.innerHTML = '<i class="fas fa-search"></i>';
            searchResults.style.display = 'none';
            document.querySelectorAll('.person-node').forEach(el => el.style.filter = '');
        }
    });

    // Hide search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target) && !searchBtn.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // ==========================================
    // 8. FETCH DATA
    // ==========================================
    function loadFamily(id) {
        loader.style.display = 'flex';
        viewport.style.opacity = '0';
        const url = `get_data.php${id ? '?id=' + id : ''}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); loader.style.display = 'none'; }
                else renderTree(data);
            })
            .catch(e => {
                console.error(e);
                loader.style.display = 'none';
            });
    }

    // ==========================================
    // 9. FULLSCREEN & RESIZE
    // ==========================================
    document.getElementById('fullscreen-btn').onclick = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
            document.getElementById('fullscreen-btn').innerHTML = '<i class="fas fa-compress"></i>';
        } else {
            document.exitFullscreen();
            document.getElementById('fullscreen-btn').innerHTML = '<i class="fas fa-expand"></i>';
        }
    };

    window.addEventListener('resize', () => {
        if (currentData) {
            viewport.style.transition = 'none';
            renderTree(currentData);
        }
    });

    // ==========================================
    // 10. START
    // ==========================================
    const urlParams = new URLSearchParams(window.location.search);
    loadFamily(urlParams.get('id'));
});

// Remove old service workers
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(regs => {
        regs.forEach(r => r.unregister());
    });
}
</script>
</body>
</html>