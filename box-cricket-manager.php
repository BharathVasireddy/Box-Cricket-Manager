<?php
/**
 * Plugin Name: Cloud 9 Box Cricket Manager
 * Description: Modern mobile-first box cricket scoring system with real-time save
 * Version: 2.0.0
 * Author: Cloud 9 Digital
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cloud9BoxCricketManager {
    
    public function __construct() {
        add_action('init', array($this, 'create_tables'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('cloud9_cricket', array($this, 'render_shortcode'));
        add_action('wp_ajax_cloud9_cricket_action', array($this, 'handle_ajax'));
        add_action('wp_ajax_nopriv_cloud9_cricket_action', array($this, 'handle_ajax'));
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cloud9_cricket_matches';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            match_name varchar(200) NOT NULL,
            match_location varchar(200),
            batting_type varchar(10) DEFAULT 'single',
            team1_name varchar(100) NOT NULL,
            team2_name varchar(100) NOT NULL,
            team1_players text,
            team2_players text,
            joker_player varchar(100),
            players_per_team int(2) DEFAULT 6,
            overs_per_team int(2) DEFAULT 6,
            team1_score int(4) DEFAULT 0,
            team1_wickets int(2) DEFAULT 0,
            team2_score int(4) DEFAULT 0,
            team2_wickets int(2) DEFAULT 0,
            current_innings int(1) DEFAULT 1,
            current_over int(2) DEFAULT 0, 
            current_ball int(1) DEFAULT 0, 
            current_batsman varchar(100),
            current_bowler varchar(100),
            match_status varchar(20) DEFAULT 'active',
            team1_player_stats text,
            team2_player_stats text,
            ball_by_ball_log text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function enqueue_assets() {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'cloud9_cricket_ajax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cloud9_cricket_nonce')
        ));
        
        add_action('wp_head', array($this, 'add_styles'));
        add_action('wp_footer', array($this, 'add_scripts'));
    }
    
    public function add_styles() {
        echo '<style>
        :root {
            --c9-primary: #F04A24;
            --c9-primary-dark: #D43918;
            --c9-primary-light: #FF6B4A;
            --c9-success: #10b981;
            --c9-danger: #ef4444;
            --c9-warning: #f59e0b;
            --c9-info: #3b82f6;
            --c9-dark: #1e293b;
            --c9-gray: #6b7280;
            --c9-light-gray: #f3f4f6;
            --c9-border: #e5e7eb;
        }
        
        .cricket-app { 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh; 
            padding: 10px; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        }
        
        .cricket-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .cricket-header {
            background: var(--c9-primary);
            background: linear-gradient(135deg, var(--c9-primary) 0%, var(--c9-primary-dark) 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cricket-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }
        
        .cricket-logo {
            height: 50px;
            margin-bottom: 15px;
            filter: brightness(0) invert(1);
            z-index: 1;
            position: relative;
        }
        
        .cricket-title { 
            font-size: 28px; 
            font-weight: 700; 
            margin: 0;
            z-index: 1;
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .cricket-subtitle {
            font-size: 16px;
            opacity: 0.95;
            margin: 8px 0 0 0;
            z-index: 1;
            position: relative;
        }
        
        .cricket-content {
            padding: 25px;
        }
        
        .cricket-form-group { 
            margin-bottom: 25px;
        }
        
        .cricket-label { 
            display: block; 
            margin-bottom: 10px; 
            font-weight: 600; 
            color: var(--c9-dark);
            font-size: 15px;
            letter-spacing: 0.3px;
        }
        
        .cricket-input, .cricket-select { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid var(--c9-border); 
            border-radius: 12px; 
            background: white; 
            color: var(--c9-dark); 
            font-size: 16px; 
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .cricket-input:focus, .cricket-select:focus {
            outline: none;
            border-color: var(--c9-primary);
            box-shadow: 0 0 0 4px rgba(240, 74, 36, 0.1);
            transform: translateY(-1px);
        }
        
        .cricket-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 25px;
        }
        
        .cricket-players-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .cricket-team-players {
            background: var(--c9-light-gray);
            padding: 20px;
            border-radius: 15px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .cricket-team-players:hover {
            border-color: var(--c9-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 74, 36, 0.1);
        }
        
        .cricket-team-title {
            font-weight: 700;
            color: var(--c9-dark);
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .cricket-team-title::before {
            content: "🏏";
        }
        
        .cricket-player-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid transparent;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 15px;
            box-sizing: border-box;
            background: white;
            transition: all 0.3s ease;
        }
        
        .cricket-player-input:focus {
            border-color: var(--c9-primary);
            box-shadow: 0 0 0 3px rgba(240, 74, 36, 0.1);
        }
        
        .cricket-btn { 
            background: var(--c9-primary);
            background: linear-gradient(135deg, var(--c9-primary) 0%, var(--c9-primary-dark) 100%);
            border: none; 
            border-radius: 12px; 
            color: white; 
            padding: 14px 28px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 4px 15px rgba(240, 74, 36, 0.3);
            letter-spacing: 0.5px;
        }
        
        .cricket-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 74, 36, 0.4);
        }
        
        .cricket-btn:active {
            transform: translateY(0);
        }
        
        .cricket-btn:disabled {
            background: var(--c9-gray);
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .cricket-btn-secondary { 
            background: var(--c9-gray);
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }
        
        .cricket-btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4);
        }
        
        .cricket-btn-danger {
            background: var(--c9-danger);
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .cricket-btn-danger:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        
        .cricket-btn-full {
            width: 100%;
        }
        
        .cricket-scorecard {
            background: white;
            border: 2px solid var(--c9-border);
            border-radius: 15px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .cricket-teams-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--c9-light-gray);
        }
        
        .cricket-team-header {
            padding: 20px;
            text-align: center;
            border-right: 2px solid var(--c9-border);
            transition: all 0.3s ease;
        }
        
        .cricket-team-header:last-child {
            border-right: none;
        }
        
        .cricket-team-header.batting {
            background: linear-gradient(135deg, rgba(240, 74, 36, 0.1) 0%, rgba(240, 74, 36, 0.05) 100%);
            position: relative;
        }
        
        .cricket-team-header.batting::before {
            content: "BATTING";
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 10px;
            background: var(--c9-primary);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .cricket-team-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--c9-dark);
            margin-bottom: 8px;
        }
        
        .cricket-team-score {
            font-size: 36px;
            font-weight: 800;
            color: var(--c9-primary);
            margin: 12px 0;
            line-height: 1;
        }
        
        .cricket-team-details {
            font-size: 14px;
            color: var(--c9-gray);
            font-weight: 500;
        }
        
        .cricket-match-status {
            padding: 20px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 2px solid var(--c9-border);
        }
        
        .cricket-over-info {
            font-size: 22px;
            font-weight: 700;
            color: var(--c9-dark);
            margin-bottom: 12px;
        }
        
        .cricket-ball-tracker {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .cricket-ball { 
            width: 32px; 
            height: 32px;
            border-radius: 50%;
            background: var(--c9-border); 
            border: 2px solid transparent;
            font-size: 14px; 
            line-height: 28px;
            text-align: center;
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .cricket-ball.dot { 
            background: var(--c9-gray);
            border-color: #4b5563;
        } 
        .cricket-ball.runs-1, .cricket-ball.runs-2, .cricket-ball.runs-3 { 
            background: var(--c9-info);
            border-color: #2563eb;
        } 
        .cricket-ball.runs-4 { 
            background: var(--c9-success);
            border-color: #059669;
        } 
        .cricket-ball.runs-6 { 
            background: var(--c9-primary);
            border-color: var(--c9-primary-dark);
            animation: bounceIn 0.5s ease;
        } 
        .cricket-ball.wicket { 
            background: var(--c9-danger);
            border-color: #dc2626;
            animation: shake 0.5s ease;
        } 
        .cricket-ball.wide, .cricket-ball.noball { 
            background: var(--c9-warning);
            color: var(--c9-dark);
            border-color: #d97706;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .cricket-scoring-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 15px; 
            margin-bottom: 25px;
        }
        
        .cricket-score-btn { 
            background: var(--c9-success);
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none; 
            border-radius: 12px; 
            color: white; 
            padding: 20px 12px; 
            font-size: 18px; 
            font-weight: 700; 
            cursor: pointer; 
            min-height: 70px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 4px;
        }
        
        .cricket-score-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .cricket-score-btn:active {
            transform: translateY(0);
        }
        
        .cricket-score-btn:disabled {
            background: var(--c9-border);
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .cricket-score-btn.boundary { 
            background: var(--c9-primary);
            background: linear-gradient(135deg, var(--c9-primary) 0%, var(--c9-primary-dark) 100%);
            box-shadow: 0 4px 15px rgba(240, 74, 36, 0.3);
        }
        
        .cricket-score-btn.boundary:hover:not(:disabled) {
            box-shadow: 0 8px 25px rgba(240, 74, 36, 0.4);
        }
        
        .cricket-score-btn.wicket { 
            background: var(--c9-danger);
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .cricket-score-btn.wicket:hover:not(:disabled) {
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }
        
        .cricket-score-btn.extra { 
            background: var(--c9-warning);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        
        .cricket-score-btn.extra:hover:not(:disabled) {
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
        }
        
        .cricket-score-btn-icon {
            font-size: 24px;
            margin-bottom: 4px;
        }
        
        .cricket-match-list { 
            display: grid; 
            gap: 16px; 
        }
        
        .cricket-match-item { 
            background: white; 
            border: 2px solid var(--c9-border); 
            border-radius: 15px; 
            padding: 20px; 
            cursor: pointer; 
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .cricket-match-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--c9-primary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .cricket-match-item:hover { 
            border-color: var(--c9-primary);
            box-shadow: 0 6px 20px rgba(240, 74, 36, 0.15);
            transform: translateY(-2px);
        }
        
        .cricket-match-item:hover::before {
            transform: scaleY(1);
        }
        
        .cricket-match-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .cricket-match-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--c9-dark);
            margin-bottom: 8px;
        }
        
        .cricket-match-location {
            color: var(--c9-gray);
            font-size: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .cricket-match-teams {
            color: var(--c9-dark);
            font-size: 17px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .cricket-match-score {
            font-size: 24px;
            font-weight: 700;
            color: var(--c9-primary);
        }
        
        .cricket-match-result {
            font-size: 16px;
            font-weight: 600;
            color: var(--c9-success);
            margin-top: 12px;
            padding: 8px 16px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 10px;
            display: inline-block;
        }
        
        .cricket-match-actions {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .cricket-delete-btn {
            background: var(--c9-danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.3s ease;
        }
        
        .cricket-delete-btn:hover {
            opacity: 1;
            transform: scale(1.05);
        }
        
        .cricket-loading { 
            text-align: center; 
            padding: 60px; 
        }
        
        .cricket-spinner { 
            border: 4px solid var(--c9-border); 
            border-top: 4px solid var(--c9-primary); 
            border-radius: 50%; 
            width: 50px; 
            height: 50px; 
            animation: spin 1s linear infinite; 
            margin: 0 auto 20px; 
        }
        
        @keyframes spin { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }
        
        .cricket-actions {
            text-align: center;
            margin-top: 25px;
        }
        
        .cricket-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 20px;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .cricket-modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .cricket-modal-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
            color: var(--c9-dark);
        }
        
        .cricket-modal-message {
            font-size: 16px;
            color: var(--c9-gray);
            text-align: center;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .cricket-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .cricket-modal-btn {
            padding: 15px;
            border: 2px solid var(--c9-border);
            border-radius: 12px;
            background: white;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            color: var(--c9-dark);
        }
        
        .cricket-modal-btn:hover {
            border-color: var(--c9-primary);
            background: rgba(240, 74, 36, 0.05);
            transform: translateY(-2px);
        }
        
        .cricket-modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .cricket-scorecard-details { 
            background: var(--c9-light-gray);
            padding: 20px;
            border-top: 2px solid var(--c9-border);
        }
        
        .cricket-current-players {
            display: grid;
            grid-template-columns: 1fr 1fr; 
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .cricket-player-info {
            text-align: center;
            background: white;
            padding: 16px;
            border-radius: 12px;
            border: 2px solid var(--c9-border);
            transition: all 0.3s ease;
        }
        
        .cricket-player-info:hover {
            border-color: var(--c9-primary);
            box-shadow: 0 4px 15px rgba(240, 74, 36, 0.1);
        }
        
        .cricket-player-label {
            font-size: 12px;
            color: var(--c9-gray);
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cricket-player-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--c9-dark);
            margin-bottom: 8px;
        }
        
        .cricket-player-stats { 
            font-size: 16px;
            color: var(--c9-primary);
            font-weight: 600;
        }
        
        .cricket-change-btn {
            background: var(--c9-gray);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .cricket-change-btn:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        .cricket-selection-screen {
            text-align: center;
            padding: 40px 20px;
        }
        
        .cricket-selection-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--c9-dark);
            margin-bottom: 30px;
        }
        
        .cricket-team-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .cricket-team-select-btn {
            background: white;
            border: 3px solid var(--c9-border);
            border-radius: 15px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .cricket-team-select-btn:hover {
            border-color: var(--c9-primary);
            background: rgba(240, 74, 36, 0.05);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(240, 74, 36, 0.15);
        }
        
        .cricket-team-select-btn.selected {
            border-color: var(--c9-primary);
            background: rgba(240, 74, 36, 0.1);
            box-shadow: 0 8px 25px rgba(240, 74, 36, 0.2);
        }
        
        .cricket-team-select-btn h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--c9-dark);
        }
        
        .cricket-team-select-btn p {
            color: var(--c9-gray);
            font-weight: 600;
        }
        
        .cricket-player-selection {
            background: white;
            border: 2px solid var(--c9-border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .cricket-player-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }
        
        .cricket-player-btn {
            background: white;
            border: 2px solid var(--c9-border);
            border-radius: 10px;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: var(--c9-dark);
        }
        
        .cricket-player-btn:hover {
            border-color: var(--c9-primary);
            background: rgba(240, 74, 36, 0.05);
            transform: translateY(-2px);
        }
        
        .cricket-player-btn.selected {
            border-color: var(--c9-primary);
            background: rgba(240, 74, 36, 0.1);
            color: var(--c9-primary);
        }
        
        .cricket-detailed-scorecard {
            margin-top: 25px;
        }
        
        .cricket-detailed-scorecard h4 {
            font-size: 20px;
            color: var(--c9-dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--c9-primary);
            font-weight: 700;
        }
        
        .cricket-scorecard-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
            margin-bottom: 25px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .cricket-scorecard-table th, .cricket-scorecard-table td {
            border: 1px solid var(--c9-border);
            padding: 12px;
            text-align: left;
        }
        
        .cricket-scorecard-table th {
            background: var(--c9-light-gray);
            font-weight: 700;
            color: var(--c9-dark);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .cricket-scorecard-table td.batsman-name {
            font-weight: 600;
            color: var(--c9-dark);
        }
        
        .cricket-scorecard-table td.batsman-out {
            font-style: italic;
            color: var(--c9-gray);
            font-size: 13px;
        }
        
        .cricket-scorecard-table tr:nth-child(even) {
            background-color: rgba(243, 244, 246, 0.5);
        }
        
        .cricket-scorecard-table tr:hover {
            background-color: rgba(240, 74, 36, 0.05);
        }
        
        .cricket-scorecard-table .total-row td {
            font-weight: 700;
            background: linear-gradient(135deg, rgba(240, 74, 36, 0.1) 0%, rgba(240, 74, 36, 0.05) 100%);
            color: var(--c9-primary);
        }
        
        .cricket-scorecard-table .extras-row td {
            font-style: italic;
            background: rgba(245, 158, 11, 0.05);
        }
        
        .cricket-fow {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            font-size: 15px;
            border: 2px solid var(--c9-border);
        }
        
        .cricket-fow h5 {
            margin: 0 0 12px 0;
            font-size: 17px;
            color: var(--c9-dark);
            font-weight: 700;
        }
        
        .cricket-fow-list {
            line-height: 1.8;
            color: var(--c9-gray);
        }
        
        .cricket-fow-item {
            font-weight: 600;
            color: var(--c9-dark);
        }
        
        .cricket-footer {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            color: var(--c9-gray);
            font-size: 14px;
            border-top: 1px solid var(--c9-border);
        }
        
        .cricket-footer-heart {
            color: var(--c9-primary);
            font-size: 16px;
            animation: heartbeat 1.5s ease-in-out infinite;
        }
        
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .cricket-app {
                padding: 5px;
            }
            
            .cricket-container {
                border-radius: 15px;
            }
            
            .cricket-header {
                padding: 20px 15px;
            }
            
            .cricket-logo {
                height: 40px;
                margin-bottom: 10px;
            }
            
            .cricket-title {
                font-size: 22px;
            }
            
            .cricket-subtitle {
                font-size: 14px;
            }
            
            .cricket-content {
                padding: 15px;
            }
            
            .cricket-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .cricket-players-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .cricket-teams-header {
                grid-template-columns: 1fr;
            }
            
            .cricket-team-header {
                border-right: none;
                border-bottom: 2px solid var(--c9-border);
                padding: 15px;
            }
            
            .cricket-team-header:last-child {
                border-bottom: none;
            }
            
            .cricket-team-score {
                font-size: 28px;
            }
            
            .cricket-current-players {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .cricket-scoring-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .cricket-score-btn {
                padding: 15px 8px;
                min-height: 60px;
                font-size: 16px;
            }
            
            .cricket-score-btn-icon {
                font-size: 20px;
            }
            
            .cricket-team-selection {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .cricket-player-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .cricket-scorecard-table {
                font-size: 13px;
            }
            
            .cricket-scorecard-table th, .cricket-scorecard-table td {
                padding: 8px 6px;
            }
            
            .cricket-scorecard-table .batsman-out {
                font-size: 11px;
            }
            
            .cricket-modal-content {
                padding: 20px;
                margin: 10px;
            }
            
            .cricket-modal-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .cricket-match-item {
                padding: 15px;
            }
            
            .cricket-match-name {
                font-size: 18px;
            }
            
            .cricket-match-score {
                font-size: 20px;
            }
            
            .cricket-match-actions {
                position: static;
                margin-top: 15px;
                text-align: right;
            }
            
            .cricket-btn {
                padding: 12px 20px;
                font-size: 15px;
            }
            
            .cricket-ball {
                width: 28px;
                height: 28px;
                font-size: 12px;
                line-height: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .cricket-scoring-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .cricket-player-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Print Styles */
        @media print {
            .cricket-btn, .cricket-change-btn, .cricket-delete-btn, .cricket-scoring-grid {
                display: none !important;
            }
            
            .cricket-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        </style>';
    }
    
    public function add_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            window.Cloud9CricketApp = {
                currentMatch: null,
                lastAction: null,
                autoSaveTimer: null,
                
                init: function() {
                    this.loadDashboard();
                    this.bindEvents();
                },
                
                bindEvents: function() {
                    $(document).on("click", ".btn-new-match", this.showNewMatchForm.bind(this));
                    $(document).on("click", ".btn-save-match", this.saveNewMatch.bind(this));
                    $(document).on("click", ".btn-back", this.loadDashboard.bind(this));
                    $(document).on("click", ".cricket-match-item", this.loadMatch.bind(this));
                    $(document).on("click", ".cricket-score-btn", this.handleScoring.bind(this));
                    $(document).on("change", "#players-per-team", this.updatePlayerInputs.bind(this));
                    $(document).on("click", ".cricket-delete-btn", this.confirmDeleteMatch.bind(this));
                    
                    $(document).on("click", ".cricket-modal", function(e) { 
                        if ($(e.target).hasClass("cricket-modal")) {
                            Cloud9CricketApp.closeModal(); 
                        }
                    });
                    $(document).on("click", ".cricket-modal-content", function(e) { e.stopPropagation(); });
                    $(document).on("click", ".btn-cancel-modal", this.closeModal.bind(this)); 
                    
                    $(document).on("click", ".cricket-team-select-btn", this.selectBattingTeam.bind(this));
                    $(document).on("click", ".btn-confirm-team", this.confirmTeamSelection.bind(this));
                    $(document).on("click", ".cricket-player-btn", this.selectPlayer.bind(this));
                    $(document).on("click", ".btn-start-match", this.confirmStartMatch.bind(this));
                    $(document).on("click", ".btn-change-batsman", this.changeBatsman.bind(this));
                    $(document).on("click", ".btn-change-bowler", this.changeBowler.bind(this));
                    $(document).on("click", ".btn-undo-last", this.undoLastAction.bind(this));
                },
                
                // Auto-save function
                autoSave: function() {
                    if (this.autoSaveTimer) {
                        clearTimeout(this.autoSaveTimer);
                    }
                    
                    this.autoSaveTimer = setTimeout(() => {
                        if (this.currentMatch && this.currentMatch.id) {
                            this.saveMatchState(null, true); // Silent save
                        }
                    }, 1000); // Save after 1 second of inactivity
                },

                _getPlayerStats: function(teamPlayers, existingStats) {
                    let stats = {};
                    if (existingStats && typeof existingStats === 'object' && Object.keys(existingStats).length > 0) {
                        stats = existingStats; 
                    }
                    if (Array.isArray(teamPlayers)) {
                        teamPlayers.forEach(player => {
                            if (!stats[player]) {
                                stats[player] = {
                                    runs: 0, balls_faced: 0, fours: 0, sixes: 0, is_out: false, out_details: 'not out',
                                    overs_bowled: 0, balls_bowled: 0, maidens: 0, runs_conceded: 0, wickets_taken: 0
                                };
                            }
                        });
                    }
                    return stats;
                },

                _parsePlayerList: function(playerListString) {
                    try {
                        if (typeof playerListString === 'string') {
                            const parsed = JSON.parse(playerListString);
                            return Array.isArray(parsed) ? parsed : [];
                        } else if (Array.isArray(playerListString)) {
                            return playerListString;
                        }
                        return [];
                    } catch (e) {
                        console.error("Error parsing player list:", e, playerListString);
                        return [];
                    }
                },
                
                loadDashboard: function() {
                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">Cloud 9 Box Cricket Manager</div>
                            <div class="cricket-subtitle">Manage your cricket matches with ease</div>
                        </div>
                        <div class="cricket-content">
                            <div class="cricket-actions">
                                <button class="cricket-btn btn-new-match">🚀 Start New Match</button>
                            </div>
                            <div class="cricket-loading">
                                <div class="cricket-spinner"></div>
                                <p>Loading matches...</p>
                            </div>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                    this.loadMatches(); 
                },
                
                loadMatches: function() {
                    $.post(cloud9_cricket_ajax.url, {
                        action: "cloud9_cricket_action",
                        cricket_action: "get_matches",
                        nonce: cloud9_cricket_ajax.nonce
                    }, function(response) { 
                        if (response.success) {
                            Cloud9CricketApp.renderMatches(response.data); 
                        } else {
                             $(".cricket-loading").html('<p style="color:var(--c9-danger);">Failed to load matches.</p>');
                        }
                    }).fail(function() { 
                        $(".cricket-loading").html('<p style="color:var(--c9-danger);">Error communicating with server.</p>');
                    });
                },
                
                confirmDeleteMatch: function(event) {
                    event.stopPropagation();
                    var matchId = $(event.currentTarget).data("id");
                    var matchName = $(event.currentTarget).data("name");
                    
                    var modalHtml = `<div class="cricket-modal">
                        <div class="cricket-modal-content">
                            <div class="cricket-modal-title" style="color: var(--c9-danger);">⚠️ Delete Match</div>
                            <div class="cricket-modal-message">
                                Are you sure you want to delete "<strong>${matchName}</strong>"?<br>
                                This action cannot be undone.
                            </div>
                            <div class="cricket-modal-actions">
                                <button class="cricket-btn cricket-btn-secondary cricket-btn-full btn-cancel-modal">Cancel</button>
                                <button class="cricket-btn cricket-btn-danger cricket-btn-full btn-confirm-delete" data-id="${matchId}">Delete Match</button>
                            </div>
                        </div>
                    </div>`;
                    $("body").append(modalHtml);
                    
                    var self = this;
                    $(".btn-confirm-delete").off("click").on("click", function() {
                        var deleteId = $(this).data("id");
                        self.deleteMatch(deleteId);
                    });
                },
                
                deleteMatch: function(matchId) {
                    var self = this;
                    $.post(cloud9_cricket_ajax.url, {
                        action: "cloud9_cricket_action",
                        cricket_action: "delete_match",
                        match_id: matchId,
                        nonce: cloud9_cricket_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            self.closeModal();
                            self.showNotification("Match deleted successfully", "success");
                            self.loadDashboard();
                        } else {
                            self.showNotification("Failed to delete match", "error");
                        }
                    }).fail(function() {
                        self.showNotification("Error deleting match", "error");
                    });
                },
                
                _getMatchResult: function(match) {
                    if (match.match_status !== 'completed') {
                        return '';
                    }
                    
                    var team1Score = parseInt(match.team1_score);
                    var team2Score = parseInt(match.team2_score);
                    var winner = "";
                    var margin = "";
                    
                    if (team1Score > team2Score) {
                        winner = match.team1_name;
                        margin = (team1Score - team2Score) + " runs";
                    } else if (team2Score > team1Score) {
                        let team2PlayersCount = 6;
                        if (match.team2_players) {
                            try {
                                let players = JSON.parse(match.team2_players);
                                if (Array.isArray(players)) {
                                    team2PlayersCount = players.length;
                                }
                            } catch (e) {}
                        }
                        let wicketsLeft = (team2PlayersCount - 1) - parseInt(match.team2_wickets);
                        wicketsLeft = Math.max(0, wicketsLeft); 
                        margin = wicketsLeft + " wicket" + (wicketsLeft !== 1 ? "s" : "");
                        winner = match.team2_name;
                    } else {
                        return "Match Tied";
                    }
                    
                    return winner + " won by " + margin;
                },
                
                renderMatches: function(matches) {
                    var matchesHtml = "";
                    if (matches && matches.length > 0) {
                        for (var i = 0; i < matches.length; i++) {
                            var match = matches[i];
                            var oversDisplay = match.current_innings == 1 ? 
                                `${match.team1_score || 0}/${match.team1_wickets || 0} (${match.current_over || 0}.${match.current_ball || 0} Ov)` :
                                `${match.team2_score || 0}/${match.team2_wickets || 0} (${match.current_over || 0}.${match.current_ball || 0} Ov)`;
                            
                            if (match.match_status === 'completed') {
                                oversDisplay = `${match.team1_name}: ${match.team1_score || 0}/${match.team1_wickets || 0} | ${match.team2_name}: ${match.team2_score || 0}/${match.team2_wickets || 0}`;
                            }
                            
                            var resultHtml = '';
                            if (match.match_status === 'completed') {
                                var result = this._getMatchResult(match);
                                resultHtml = `<div class="cricket-match-result">🏆 ${result}</div>`;
                            }

                            matchesHtml += `<div class="cricket-match-item" data-id="${match.id}">
                                <div class="cricket-match-header">
                                    <div>
                                        <div class="cricket-match-name">${match.match_name}</div>
                                        <div class="cricket-match-location">📍 ${match.match_location || "N/A"}</div>
                                    </div>
                                    <div class="cricket-match-actions">
                                        <button class="cricket-delete-btn" data-id="${match.id}" data-name="${match.match_name}">🗑️</button>
                                    </div>
                                </div>
                                <div class="cricket-match-teams">${match.team1_name} vs ${match.team2_name}</div>
                                <div class="cricket-match-score">${oversDisplay}</div>
                                ${resultHtml}
                                <div style="font-size:13px; color:var(--c9-gray); margin-top:8px;">
                                    Status: <span style="font-weight:600;">${match.match_status === 'completed' ? '✅ Completed' : '🏏 In Progress'}</span>
                                </div>
                            </div>`;
                        }
                    } else {
                        matchesHtml = '<div style="text-align:center;color:var(--c9-gray);padding:60px;"><p style="font-size:18px;margin-bottom:10px;">No matches found</p><p>Start your first match to begin!</p></div>';
                    }
                    
                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">Cloud 9 Box Cricket Manager</div>
                            <div class="cricket-subtitle">Manage your cricket matches with ease</div>
                        </div>
                        <div class="cricket-content">
                            <div class="cricket-actions">
                                <button class="cricket-btn btn-new-match">🚀 Start New Match</button>
                            </div>
                            <div class="cricket-match-list">${matchesHtml}</div>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                },
                
                showNewMatchForm: function() {
                    var playerOptionsHtml = "";
                    for (let i = 2; i <= 8; i++) { 
                        playerOptionsHtml += `<option value="${i}" ${i === 6 ? 'selected' : ''}>${i} Players</option>`;
                    }

                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">New Match</div>
                            <div class="cricket-subtitle">Set up your cricket match</div>
                        </div>
                        <div class="cricket-content">
                            <button class="cricket-btn cricket-btn-secondary btn-back" style="margin-bottom:25px;">← Back to Dashboard</button>
                            <form class="cricket-form">
                                <div class="cricket-form-group">
                                    <label class="cricket-label">Match Name</label>
                                    <input class="cricket-input" id="match-name" placeholder="Sunday Evening Match" required>
                                </div>
                                <div class="cricket-form-group">
                                    <label class="cricket-label">Location</label>
                                    <input class="cricket-input" id="match-location" placeholder="Community Ground, Guntur">
                                </div>
                                <div class="cricket-row">
                                    <div class="cricket-form-group">
                                        <label class="cricket-label">Batting Style</label>
                                        <select class="cricket-select" id="batting-type">
                                            <option value="single">🏏 Single Batting</option>
                                            <option value="double">👥 Double Batting</option>
                                        </select>
                                    </div>
                                    <div class="cricket-form-group">
                                        <label class="cricket-label">Players per Team</label>
                                        <select class="cricket-select" id="players-per-team">${playerOptionsHtml}</select>
                                    </div>
                                </div>
                                <div class="cricket-form-group" style="background:rgba(240, 74, 36, 0.05);padding:20px;border-radius:12px;border:2px solid var(--c9-primary);">
                                    <label class="cricket-label" style="color:var(--c9-primary);font-size:17px;font-weight:700;">⏱️ Match Duration - Overs per Team</label>
                                    <input class="cricket-input" type="number" id="overs-per-team" min="1" max="50" value="6" required style="border-color:var(--c9-primary);font-size:20px;font-weight:700;text-align:center;">
                                    <small style="color:var(--c9-primary);font-weight:600;">Choose between 1-50 overs per team</small>
                                </div>
                                <div class="cricket-row">
                                    <div class="cricket-form-group">
                                        <label class="cricket-label">Team 1 Name</label>
                                        <input class="cricket-input" id="team1-name" placeholder="Warriors" required>
                                    </div>
                                    <div class="cricket-form-group">
                                        <label class="cricket-label">Team 2 Name</label>
                                        <input class="cricket-input" id="team2-name" placeholder="Champions" required>
                                    </div>
                                </div>
                                <div class="cricket-players-section">
                                    <div class="cricket-team-players">
                                        <div class="cricket-team-title">Team 1 Players</div>
                                        <div id="team1-players">${this.generatePlayerInputs("team1", 6)}</div>
                                    </div>
                                    <div class="cricket-team-players">
                                        <div class="cricket-team-title">Team 2 Players</div>
                                        <div id="team2-players">${this.generatePlayerInputs("team2", 6)}</div>
                                    </div>
                                </div>
                                <div class="cricket-form-group">
                                    <label class="cricket-label">Joker Player (Optional)</label>
                                    <input class="cricket-input" id="joker-player" placeholder="Player who can play for both teams">
                                </div>
                                <div class="cricket-actions">
                                    <button type="button" class="cricket-btn cricket-btn-full btn-save-match">🚀 Create Match</button>
                                </div>
                            </form>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                },
                
                generatePlayerInputs: function(teamPrefix, count) {
                    var html = "";
                    for (var i = 1; i <= count; i++) {
                        html += `<input class="cricket-player-input" id="${teamPrefix}-player-${i}" placeholder="Player ${i} name" required>`;
                    }
                    return html;
                },
                
                updatePlayerInputs: function() {
                    var playerCount = parseInt($("#players-per-team").val());
                    $("#team1-players").html(this.generatePlayerInputs("team1", playerCount)); 
                    $("#team2-players").html(this.generatePlayerInputs("team2", playerCount)); 
                },
                
                saveNewMatch: function() { 
                    var playerCount = parseInt($("#players-per-team").val());
                    var team1Players = [];
                    var team2Players = [];
                    
                    for (var i = 1; i <= playerCount; i++) {
                        var player1 = $("#team1-player-" + i).val().trim();
                        var player2 = $("#team2-player-" + i).val().trim();
                        if (!player1 || !player2) {
                            this.showNotification("Please enter all player names", "error"); 
                            return;
                        }
                        team1Players.push(player1);
                        team2Players.push(player2);
                    }
                    
                    var data = {
                        action: "cloud9_cricket_action",
                        cricket_action: "create_match",
                        nonce: cloud9_cricket_ajax.nonce,
                        match_name: $("#match-name").val(),
                        match_location: $("#match-location").val(),
                        batting_type: $("#batting-type").val(),
                        team1_name: $("#team1-name").val(),
                        team2_name: $("#team2-name").val(),
                        team1_players: JSON.stringify(team1Players),
                        team2_players: JSON.stringify(team2Players),
                        joker_player: $("#joker-player").val(),
                        players_per_team: playerCount,
                        overs_per_team: $("#overs-per-team").val(),
                        team1_player_stats: JSON.stringify(this._getPlayerStats(team1Players, {})), 
                        team2_player_stats: JSON.stringify(this._getPlayerStats(team2Players, {}))  
                    };
                    
                    var self = this; 
                    $.post(cloud9_cricket_ajax.url, data, function(response) {
                        if (response.success) {
                            self.currentMatch = response.data;
                            self.currentMatch.team1_player_stats = self._parsePlayerStats(self.currentMatch.team1_player_stats);
                            self.currentMatch.team2_player_stats = self._parsePlayerStats(self.currentMatch.team2_player_stats);
                            self.currentMatch.ball_by_ball_log = self._parseBallLog(self.currentMatch.ball_by_ball_log);
                            self.showTeamSelection();
                        } else {
                            self.showNotification("Failed to create match: " + (response.data || "Unknown error"), "error");
                        }
                    }).fail(function() {
                        self.showNotification("Error communicating with server", "error");
                    });
                },

                _parsePlayerStats: function(statsString) {
                    try {
                        if (typeof statsString === 'string') return JSON.parse(statsString);
                        if (typeof statsString === 'object' && statsString !== null) return statsString; 
                        return {};
                    } catch (e) { 
                        console.error("Error parsing player stats:", e, statsString);
                        return {}; 
                    }
                },
                
                _parseBallLog: function(logString) {
                    try {
                        if (typeof logString === 'string') return JSON.parse(logString);
                        if (Array.isArray(logString)) return logString;
                        return [];
                    } catch (e) { 
                        console.error("Error parsing ball log:", e, logString);
                        return []; 
                    }
                },
                
                showTeamSelection: function() {
                    var match = this.currentMatch; 
                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">Team Selection</div>
                            <div class="cricket-subtitle">Choose which team bats first</div>
                        </div>
                        <div class="cricket-content">
                            <div class="cricket-selection-screen">
                                <div class="cricket-selection-title">Which team will bat first?</div>
                                <div class="cricket-team-selection">
                                    <div class="cricket-team-select-btn" data-team="1">
                                        <h3>${match.team1_name}</h3>
                                        <p>Bats First</p>
                                    </div>
                                    <div class="cricket-team-select-btn" data-team="2">
                                        <h3>${match.team2_name}</h3>
                                        <p>Bats First</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                },
                
                selectBattingTeam: function(event) { 
                    var selectedTeam = $(event.currentTarget).data("team"); 
                    $(".cricket-team-select-btn").removeClass("selected");
                    $(event.currentTarget).addClass("selected");
                    
                    if (!$(".btn-confirm-team").length) {
                        var confirmBtn = `<div style="text-align:center;margin-top:25px;">
                            <button class="cricket-btn btn-confirm-team">Confirm Selection</button>
                        </div>`;
                        $(".cricket-team-selection").after(confirmBtn);
                    }
                    $(".btn-confirm-team").data("team", selectedTeam); 
                },
                
                confirmTeamSelection: function() {
                    var selectedTeam = $(".btn-confirm-team").data("team");
                    if (!selectedTeam) {
                        this.showNotification("Please select a team to bat first", "error"); 
                        return;
                    }

                    var teamName = selectedTeam == 1 ? this.currentMatch.team1_name : this.currentMatch.team2_name; 
                    
                    var self = this; 
                    this.showConfirm(`Confirm that ${teamName} will bat first?`, function() {
                        self.currentMatch.current_innings = parseInt(selectedTeam);
                        self.currentMatch.current_over = 0; 
                        self.currentMatch.current_ball = 0; 
                        self.saveMatchState(function(success) { 
                           if(success) self.showPlayerSelection(false); 
                        });
                    });
                },
                
                showPlayerSelection: function(isSecondInningsFlag = false) { 
                    var match = this.currentMatch; 
                    var battingTeamId = parseInt(match.current_innings); 
                    var bowlingTeamId = battingTeamId === 1 ? 2 : 1;

                    var battingTeamName = battingTeamId === 1 ? match.team1_name : match.team2_name;
                    var bowlingTeamName = bowlingTeamId === 1 ? match.team1_name : match.team2_name;
                    
                    var battingPlayersList = this._parsePlayerList(battingTeamId === 1 ? match.team1_players : match.team2_players);
                    var bowlingPlayersList = this._parsePlayerList(bowlingTeamId === 1 ? match.team1_players : match.team2_players);

                    var batsmanOptions = "";
                    battingPlayersList.forEach(function(player) {
                        batsmanOptions += `<div class="cricket-player-btn" data-player="${player}">${player}</div>`;
                    });
                    if (match.joker_player && match.joker_player.trim() && !battingPlayersList.includes(match.joker_player)) {
                        batsmanOptions += `<div class="cricket-player-btn" data-player="${match.joker_player}">🃏 ${match.joker_player}</div>`;
                    }

                    var bowlerOptions = "";
                    bowlingPlayersList.forEach(function(player) {
                        bowlerOptions += `<div class="cricket-player-btn" data-player="${player}">${player}</div>`;
                    });
                     if (match.joker_player && match.joker_player.trim() && !bowlingPlayersList.includes(match.joker_player)) {
                        bowlerOptions += `<div class="cricket-player-btn" data-player="${match.joker_player}">🃏 ${match.joker_player}</div>`;
                    }
                    
                    var title = isSecondInningsFlag ? "Second Innings" : "Player Selection";
                    var buttonText = isSecondInningsFlag ? 'Start Second Innings' : 'Start Match';

                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">${title}</div>
                            <div class="cricket-subtitle">Choose batsman and bowler</div>
                        </div>
                        <div class="cricket-content">
                            <div class="cricket-player-selection">
                                <div class="cricket-label">Select Batsman (${battingTeamName})</div>
                                <div class="cricket-player-grid" data-selection="batsman">${batsmanOptions}</div>
                            </div>
                            <div class="cricket-player-selection">
                                <div class="cricket-label">Select Bowler (${bowlingTeamName})</div>
                                <div class="cricket-player-grid" data-selection="bowler">${bowlerOptions}</div>
                            </div>
                            <div class="cricket-actions" style="margin-top:25px;">
                                <button class="cricket-btn cricket-btn-full btn-start-match" disabled>${buttonText}</button>
                            </div>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                },
                
                selectPlayer: function(event) { 
                    var selectionType = $(event.currentTarget).closest(".cricket-player-grid").data("selection"); 
                    var playerName = $(event.currentTarget).data("player");
                    
                    $(event.currentTarget).closest(".cricket-player-grid").find(".cricket-player-btn").removeClass("selected");
                    $(event.currentTarget).addClass("selected");
                    
                    if (selectionType === "batsman") {
                        this.currentMatch.current_batsman = playerName; 
                    } else if (selectionType === "bowler") {
                        this.currentMatch.current_bowler = playerName; 
                    }
                    
                    if (this.currentMatch.current_batsman && this.currentMatch.current_bowler) { 
                        $(".btn-start-match").prop("disabled", false);
                    } else {
                        $(".btn-start-match").prop("disabled", true);
                    }
                },
                
                confirmStartMatch: function() {
                    var match = this.currentMatch; 
                    if (!match.current_batsman || !match.current_bowler) {
                        this.showNotification("Please select both batsman and bowler", "error"); 
                        return;
                    }
                    
                    var self = this; 
                    self.lastAction = null; 
                    match.team1_player_stats = self._getPlayerStats(self._parsePlayerList(match.team1_players), self._parsePlayerStats(match.team1_player_stats));
                    match.team2_player_stats = self._getPlayerStats(self._parsePlayerList(match.team2_players), self._parsePlayerStats(match.team2_player_stats));
                    match.ball_by_ball_log = self._parseBallLog(match.ball_by_ball_log || "[]");

                    self.saveMatchState(function(success) {
                        if(success) self.renderMatch();
                    });
                },
                
                loadMatch: function(event) { 
                    var matchIdFromClick = event && event.currentTarget ? $(event.currentTarget).data("id") : null;
                    var matchId = matchIdFromClick || (this.currentMatch ? this.currentMatch.id : null);

                    if(!matchId) { 
                        if(this.currentMatch) { 
                            this.currentMatch.team1_player_stats = this._parsePlayerStats(this.currentMatch.team1_player_stats);
                            this.currentMatch.team2_player_stats = this._parsePlayerStats(this.currentMatch.team2_player_stats);
                            this.currentMatch.ball_by_ball_log = this._parseBallLog(this.currentMatch.ball_by_ball_log);

                            if (!this.currentMatch.current_batsman || !this.currentMatch.current_bowler) {
                                this.showPlayerSelection(parseInt(this.currentMatch.current_innings) === 2 && parseInt(this.currentMatch.current_over) === 0 && parseInt(this.currentMatch.current_ball) === 0);
                            } else {
                                this.renderMatch();
                            }
                            return;
                        } else {
                            this.showNotification("Error: No match ID available", "error");
                            this.loadDashboard();
                            return;
                        }
                    }
                    
                    var self = this; 
                    $.post(cloud9_cricket_ajax.url, {
                        action: "cloud9_cricket_action", 
                        cricket_action: "get_match",
                        match_id: matchId,
                        nonce: cloud9_cricket_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            self.currentMatch = response.data;
                            self.currentMatch.team1_player_stats = self._parsePlayerStats(self.currentMatch.team1_player_stats);
                            self.currentMatch.team2_player_stats = self._parsePlayerStats(self.currentMatch.team2_player_stats);
                            self.currentMatch.ball_by_ball_log = self._parseBallLog(self.currentMatch.ball_by_ball_log);
                            
                            if (self.currentMatch.match_status === 'active' && (!self.currentMatch.current_batsman || !self.currentMatch.current_bowler) && parseInt(self.currentMatch.current_over) === 0 && parseInt(self.currentMatch.current_ball) === 0 ) {
                                self.showPlayerSelection(parseInt(self.currentMatch.current_innings) === 2);
                            } else {
                                self.renderMatch();
                            }
                        } else {
                            self.showNotification("Failed to load match", "error");
                            self.loadDashboard();
                        }
                    }).fail(function() {
                        self.showNotification("Error communicating with server", "error");
                        self.loadDashboard();
                    });
                },

                _generateBattingScorecardHtml: function(teamName, teamPlayersList, playerStats, currentBatsman, includeFallOfWickets = true) {
                    var match = this.currentMatch;
                    if (!match) return '<div>No match data available</div>';

                    let html = `<h4>${teamName} - Batting</h4>
                                <table class="cricket-scorecard-table">
                                    <thead>
                                        <tr>
                                            <th>Batsman</th>
                                            <th>Status</th>
                                            <th>R</th>
                                            <th>B</th>
                                            <th>4s</th>
                                            <th>6s</th>
                                            <th>SR</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                    
                    let totalRuns = 0;
                    let totalBalls = 0;
                    let totalWickets = 0;
                    let inningsNumber = (teamName === match.team1_name) ? 1 : 2;

                    teamPlayersList.forEach(playerName => {
                        const stats = playerStats[playerName] || this._getPlayerStats([playerName], {})[playerName];
                        const status = stats.is_out ? (stats.out_details || 'out') : 'not out';
                        const runs = stats.runs || 0;
                        const balls = stats.balls_faced || 0;
                        const fours = stats.fours || 0;
                        const sixes = stats.sixes || 0;
                        const sr = balls > 0 ? ((runs / balls) * 100).toFixed(2) : "0.00";
                        
                        totalRuns += runs;
                        totalBalls += balls;
                        if (stats.is_out) totalWickets++;

                        html += `<tr>
                                    <td class="batsman-name">${playerName} ${playerName === currentBatsman && !stats.is_out ? '*' : ''}</td>
                                    <td class="batsman-out">${status}</td>
                                    <td>${runs}</td>
                                    <td>${balls}</td>
                                    <td>${fours}</td>
                                    <td>${sixes}</td>
                                    <td>${sr}</td>
                                </tr>`;
                    });

                    let extras = {
                        total: 0,
                        wides: 0,
                        noBalls: 0
                    };

                    if (match.ball_by_ball_log && match.ball_by_ball_log.length > 0) {
                        const inningsLog = match.ball_by_ball_log.filter(b => b.innings === inningsNumber);
                        
                        inningsLog.forEach(ball => {
                            if (ball.is_wide) {
                                extras.wides += ball.runs_scored;
                                extras.total += ball.runs_scored;
                            }
                            else if (ball.is_noball) {
                                extras.noBalls += 1;
                                extras.total += 1;
                            }
                        });
                    }

                    const officialWickets = (teamName === match.team1_name) ? (match.team1_wickets || 0) : (match.team2_wickets || 0);
                    const officialScore = (teamName === match.team1_name) ? (match.team1_score || 0) : (match.team2_score || 0);

                    html += `<tr class="extras-row">
                                <td colspan="2">Extras</td>
                                <td colspan="5">${extras.total} (WD: ${extras.wides}, NB: ${extras.noBalls})</td>
                            </tr>`;

                    html += `<tr class="total-row">
                                <td colspan="2">Total</td>
                                <td colspan="5">${officialScore}/${officialWickets}</td>
                            </tr>`;
                    html += `</tbody></table>`;

                    if (includeFallOfWickets) {
                        const fallOfWickets = this._getFallOfWickets(inningsNumber);
                        if (fallOfWickets.length > 0) {
                            html += `<div class="cricket-fow">
                                        <h5>Fall of Wickets</h5>
                                        <div class="cricket-fow-list">`;
                            
                            fallOfWickets.forEach((wicket, index) => {
                                html += `<span class="cricket-fow-item">${index + 1}-${wicket.score} (${wicket.player}, ${wicket.over}.${wicket.ball})</span>`;
                                if (index < fallOfWickets.length - 1) {
                                    html += `, `;
                                }
                            });
                            
                            html += `</div></div>`;
                        }
                    }

                    return html;
                },

                _generateBowlingScorecardHtml: function(teamName, teamPlayersList, playerStats, currentBowler) {
                    let html = `<h4>${teamName} - Bowling</h4>
                                <table class="cricket-scorecard-table">
                                    <thead>
                                        <tr>
                                            <th>Bowler</th>
                                            <th>O</th>
                                            <th>M</th>
                                            <th>R</th>
                                            <th>W</th>
                                            <th>Econ</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                    let hasBowlers = false;
                    teamPlayersList.forEach(playerName => {
                        const stats = playerStats[playerName] || this._getPlayerStats([playerName], {})[playerName];
                        if (stats.balls_bowled > 0 || playerName === currentBowler) {
                            hasBowlers = true;
                            const overs = Math.floor(stats.balls_bowled / 6);
                            const ballsThisOver = stats.balls_bowled % 6;
                            const oversDisplay = `${overs}.${ballsThisOver}`;
                            const maidens = stats.maidens || 0;
                            const runs = stats.runs_conceded || 0;
                            const wickets = stats.wickets_taken || 0;
                            const econ = stats.balls_bowled > 0 ? ((runs / (stats.balls_bowled / 6))).toFixed(2) : "0.00";
                            
                            html += `<tr>
                                        <td class="batsman-name">${playerName} ${playerName === currentBowler ? '*' : ''}</td>
                                        <td>${oversDisplay}</td>
                                        <td>${maidens}</td>
                                        <td>${runs}</td>
                                        <td>${wickets}</td>
                                        <td>${econ}</td>
                                     </tr>`;
                        }
                    });
                    if (!hasBowlers) {
                        html += `<tr><td colspan="6" style="text-align:center;color:var(--c9-gray);">Yet to bowl</td></tr>`;
                    }
                    html += `   </tbody>
                                </table>`;
                    return html;
                },

                _getFallOfWickets: function(inningsNumber) {
                    const match = this.currentMatch;
                    if (!match || !match.ball_by_ball_log) return [];

                    const fallOfWickets = [];
                    
                    const wickets = match.ball_by_ball_log.filter(ball => 
                        ball.innings === inningsNumber && 
                        ball.is_wicket === true
                    );

                    wickets.sort((a, b) => {
                        if (a.over !== b.over) return a.over - b.over;
                        return a.ball_in_over - b.ball_in_over;
                    });

                    let runningScore = 0;
                    match.ball_by_ball_log
                        .filter(ball => ball.innings === inningsNumber)
                        .sort((a, b) => {
                            if (a.over !== b.over) return a.over - b.over;
                            return a.ball_in_over - b.ball_in_over;
                        })
                        .forEach(ball => {
                            const isProcessedWicket = fallOfWickets.some(w => 
                                w.over === ball.over && 
                                w.ball === ball.ball_in_over && 
                                w.player === ball.batsman
                            );

                            if (isProcessedWicket) return;

                            runningScore += ball.runs_scored;

                            if (ball.is_wicket) {
                                fallOfWickets.push({
                                    player: ball.batsman,
                                    score: runningScore,
                                    over: ball.over,
                                    ball: ball.ball_in_over
                                });
                            }
                        });

                    return fallOfWickets;
                },
                
                renderMatch: function() {
                    var match = this.currentMatch; 
                    if (!match) {
                        this.showNotification("Error: No match data", "error"); 
                        this.loadDashboard(); 
                        return;
                    }

                    var ballTrackerHtml = "";
                    var currentOverBalls = [];
                    if (match.ball_by_ball_log && match.ball_by_ball_log.length > 0) {
                        const currentOverLog = match.ball_by_ball_log.filter(b => 
                            b.over === parseInt(match.current_over) && 
                            b.innings === parseInt(match.current_innings)
                        );
                        
                        currentOverBalls = currentOverLog.map(b => {
                            if (b.is_wicket) return {type: 'wicket', display: 'W'};
                            if (b.is_wide) return {type: 'wide', display: 'WD'};
                            if (b.is_noball) return {type: 'noball', display: `NB`};
                            if (b.runs_scored === 0 && !b.is_wide && !b.is_noball) return {type: 'dot', display: '•'};
                            return {type: `runs-${b.runs_scored}`, display: `${b.runs_scored}`};
                        });
                    }

                    for (var i = 0; i < 6; i++) {
                        if (i < currentOverBalls.length) {
                            ballTrackerHtml += `<div class="cricket-ball ${currentOverBalls[i].type}">${currentOverBalls[i].display}</div>`;
                        } else {
                            ballTrackerHtml += '<div class="cricket-ball"></div>';
                        }
                    }
                    
                    var battingTeamId = parseInt(match.current_innings);
                    var bowlingTeamId = battingTeamId === 1 ? 2 : 1;
                    
                    var team1ScoreDisplay = `${match.team1_score || 0}/${match.team1_wickets || 0}`;
                    var team2ScoreDisplay = `${match.team2_score || 0}/${match.team2_wickets || 0}`;
                    
                    var batsmanStatsDisplay = "R: 0, B: 0";
                    var bowlerStatsDisplay = "O: 0.0, R: 0, W: 0";

                    const currentBattingTeamStats = battingTeamId === 1 ? this._parsePlayerStats(match.team1_player_stats) : this._parsePlayerStats(match.team2_player_stats);
                    const currentBowlingTeamStats = bowlingTeamId === 1 ? this._parsePlayerStats(match.team1_player_stats) : this._parsePlayerStats(match.team2_player_stats);

                    if (match.current_batsman && currentBattingTeamStats && currentBattingTeamStats[match.current_batsman]) {
                        const stats = currentBattingTeamStats[match.current_batsman];
                        batsmanStatsDisplay = `R: ${stats.runs}, B: ${stats.balls_faced}`;
                    }
                    if (match.current_bowler && currentBowlingTeamStats && currentBowlingTeamStats[match.current_bowler]) {
                        const stats = currentBowlingTeamStats[match.current_bowler];
                        const bowlerOvers = Math.floor(stats.balls_bowled / 6);
                        const bowlerBalls = stats.balls_bowled % 6;
                        bowlerStatsDisplay = `O: ${bowlerOvers}.${bowlerBalls}, R: ${stats.runs_conceded}, W: ${stats.wickets_taken}`;
                    }

                    var rrInfo = "";
                    if (battingTeamId === 2 && match.match_status === 'active') { 
                        var target = parseInt(match.team1_score) + 1;
                        var needed = target - parseInt(match.team2_score);
                        var totalBallsInMatch = parseInt(match.overs_per_team) * 6;
                        var ballsBowledInCurrentOver = parseInt(match.current_ball); 
                        var ballsBowledInning2 = (parseInt(match.current_over) * 6) + ballsBowledInCurrentOver; 
                        var ballsLeft = totalBallsInMatch - ballsBowledInning2;
                        
                        var rrRequired = "N/A";
                        if (needed <=0) {
                            rrRequired = "Target Achieved";
                        } else if (ballsLeft > 0 ) {
                             rrRequired = (needed / ballsLeft * 6).toFixed(2);
                        } else if (ballsLeft <= 0 && needed > 0) {
                            rrRequired = "Needed " + needed;
                        }
                        rrInfo = `<div style="text-align:center;margin-top:10px;color:var(--c9-gray);font-size:15px;font-weight:600;">
                            🎯 Target: ${target} | Need ${needed > 0 ? needed : 0} runs in ${ballsLeft > 0 ? ballsLeft : 0} balls (RRR: ${rrRequired})
                        </div>`;
                    }
                    
                    var undoBtn = this.lastAction ? '<button class="cricket-btn cricket-btn-secondary btn-undo-last">↶ Undo</button>' : "";
                    var scoringDisabled = (!match.current_batsman || !match.current_bowler) ? "disabled" : "";

                    let battingScorecardHtml = "";
                    let bowlingScorecardHtml = "";
                    if (battingTeamId === 1) {
                        battingScorecardHtml = this._generateBattingScorecardHtml(match.team1_name, this._parsePlayerList(match.team1_players), currentBattingTeamStats, match.current_batsman);
                        bowlingScorecardHtml = this._generateBowlingScorecardHtml(match.team2_name, this._parsePlayerList(match.team2_players), currentBowlingTeamStats, match.current_bowler);
                    } else {
                        battingScorecardHtml = this._generateBattingScorecardHtml(match.team2_name, this._parsePlayerList(match.team2_players), currentBattingTeamStats, match.current_batsman);
                        bowlingScorecardHtml = this._generateBowlingScorecardHtml(match.team1_name, this._parsePlayerList(match.team1_players), currentBowlingTeamStats, match.current_bowler);
                    }
                    
                    var scoringControlsHtml = '';
                    if (match.match_status !== 'completed') {
                        scoringControlsHtml = `
                            <div style="text-align:center;margin-bottom:20px;">
                                ${undoBtn}
                            </div>
                            <div class="cricket-scoring-grid">
                                <button class="cricket-score-btn" data-runs="0" data-type="runs" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">0</div>
                                    <div>Dot Ball</div>
                                </button>
                                <button class="cricket-score-btn" data-runs="1" data-type="runs" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">1</div>
                                    <div>Single</div>
                                </button>
                                <button class="cricket-score-btn" data-runs="2" data-type="runs" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">2</div>
                                    <div>Double</div>
                                </button>
                                <button class="cricket-score-btn" data-runs="3" data-type="runs" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">3</div>
                                    <div>Three</div>
                                </button>
                                <button class="cricket-score-btn boundary" data-runs="4" data-type="runs" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">4️⃣</div>
                                    <div>Four</div>
                                </button>
                                <button class="cricket-score-btn boundary" data-runs="6" data-type="runs" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">6️⃣</div>
                                    <div>Six</div>
                                </button>
                                <button class="cricket-score-btn wicket" data-type="wicket" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">🔴</div>
                                    <div>Wicket</div>
                                </button>
                                <button class="cricket-score-btn extra" data-type="wide" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">WD</div>
                                    <div>Wide</div>
                                </button>
                                <button class="cricket-score-btn extra" data-type="noball" ${scoringDisabled}>
                                    <div class="cricket-score-btn-icon">NB</div>
                                    <div>No Ball</div>
                                </button>
                            </div>
                        `;
                    } else {
                        let resultMessage = this._getMatchResult(match);
                        scoringControlsHtml = `
                            <div style="text-align:center;margin:30px 0;">
                                <div class="cricket-match-result" style="font-size:22px;padding:20px;margin-bottom:20px;">🏆 ${resultMessage}</div>
                                <button class="cricket-btn btn-back">Back to Dashboard</button>
                            </div>
                        `;
                    }
                    
                    var currentPlayersHtml = '';
                    if (match.match_status !== 'completed') {
                        currentPlayersHtml = `
                            <div class="cricket-current-players">
                                <div class="cricket-player-info">
                                    <div class="cricket-player-label">Batsman</div>
                                    <div class="cricket-player-name">${match.current_batsman || "Not Selected"}</div>
                                    <div class="cricket-player-stats">${batsmanStatsDisplay}</div>
                                    <button class="cricket-change-btn btn-change-batsman">Change</button>
                                </div>
                                <div class="cricket-player-info">
                                    <div class="cricket-player-label">Bowler</div>
                                    <div class="cricket-player-name">${match.current_bowler || "Not Selected"}</div>
                                    <div class="cricket-player-stats">${bowlerStatsDisplay}</div>
                                    <button class="cricket-change-btn btn-change-bowler">Change</button>
                                </div>
                            </div>
                        `;
                    }
                    
                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">${match.match_name}</div>
                            <div class="cricket-subtitle">📍 ${match.match_location || "N/A"}</div>
                        </div>
                        <div class="cricket-content">
                            <button class="cricket-btn cricket-btn-secondary btn-back" style="margin-bottom:25px;">← Back to Dashboard</button>
                            <div class="cricket-scorecard">
                                <div class="cricket-teams-header">
                                    <div class="cricket-team-header ${battingTeamId == 1 ? "batting" : ""}">
                                        <div class="cricket-team-name">${match.team1_name}</div>
                                        <div class="cricket-team-score">${team1ScoreDisplay}</div>
                                        <div class="cricket-team-details">${match.overs_per_team} overs</div>
                                    </div>
                                    <div class="cricket-team-header ${battingTeamId == 2 ? "batting" : ""}">
                                        <div class="cricket-team-name">${match.team2_name}</div>
                                        <div class="cricket-team-score">${team2ScoreDisplay}</div>
                                        <div class="cricket-team-details">${match.overs_per_team} overs</div>
                                    </div>
                                </div>
                                <div class="cricket-match-status">
                                    <div class="cricket-over-info">Over ${match.current_over}.${match.current_ball}</div>
                                    <div class="cricket-ball-tracker">${ballTrackerHtml}</div>
                                    ${rrInfo}
                                </div>
                                <div class="cricket-scorecard-details">
                                    ${currentPlayersHtml}
                                    <div class="cricket-detailed-scorecard">
                                        ${battingScorecardHtml}
                                        ${bowlingScorecardHtml}
                                    </div>
                                </div>
                            </div>
                            ${scoringControlsHtml}
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                },
                
                changeBatsman: function() {
                    var match = this.currentMatch; 
                    var battingTeamId = parseInt(match.current_innings);
                    var battingPlayersList = this._parsePlayerList(battingTeamId == 1 ? match.team1_players : match.team2_players);
                    
                    var playerOptions = "";
                    battingPlayersList.forEach(function(player) {
                        const playerStats = (battingTeamId === 1 ? Cloud9CricketApp._parsePlayerStats(match.team1_player_stats) : Cloud9CricketApp._parsePlayerStats(match.team2_player_stats));
                        if (player !== match.current_batsman && (!playerStats[player] || !playerStats[player].is_out)) { 
                            playerOptions += `<div class="cricket-modal-btn" data-player="${player}">${player}</div>`;
                        }
                    });
                    if (match.joker_player && match.joker_player.trim() && match.joker_player !== match.current_batsman) {
                         const jokerStats = (battingTeamId === 1 ? Cloud9CricketApp._parsePlayerStats(match.team1_player_stats) : Cloud9CricketApp._parsePlayerStats(match.team2_player_stats))[match.joker_player];
                         if(!battingPlayersList.find(p => p === match.joker_player && p !== match.current_batsman) && (!jokerStats || !jokerStats.is_out) ) { 
                            playerOptions += `<div class="cricket-modal-btn" data-player="${match.joker_player}">🃏 ${match.joker_player}</div>`;
                         }
                    }
                    
                    var modalHtml = `<div class="cricket-modal">
                        <div class="cricket-modal-content">
                            <div class="cricket-modal-title">Select New Batsman</div>
                            <div class="cricket-modal-grid">${playerOptions || "<p>No other batsmen available.</p>"}</div>
                            <button class="cricket-btn cricket-btn-secondary cricket-btn-full btn-cancel-modal">Cancel</button>
                        </div>
                    </div>`;
                    $("body").append(modalHtml);
                    
                    var self = this; 
                    $(".cricket-modal .cricket-modal-btn").off("click").on("click", function() {
                        var newBatsman = $(this).data("player");
                        self.currentMatch.current_batsman = newBatsman;
                        self.saveMatchState(function(success) {
                           if(success) {
                                self.closeModal();
                                self.renderMatch();
                           }
                        });
                    });
                },
                
                changeBowler: function() {
                    var match = this.currentMatch; 
                    var bowlingTeamId = parseInt(match.current_innings) == 1 ? 2 : 1;
                    var bowlingPlayersList = this._parsePlayerList(bowlingTeamId == 1 ? match.team1_players : match.team2_players);
                    
                    var playerOptions = "";
                    bowlingPlayersList.forEach(function(player) {
                        if (player !== match.current_bowler) { 
                            playerOptions += `<div class="cricket-modal-btn" data-player="${player}">${player}</div>`;
                        }
                    });
                     if (match.joker_player && match.joker_player.trim() && match.joker_player !== match.current_bowler) {
                        if(!bowlingPlayersList.find(p => p === match.joker_player && p !== match.current_bowler)) { 
                            playerOptions += `<div class="cricket-modal-btn" data-player="${match.joker_player}">🃏 ${match.joker_player}</div>`;
                        }
                    }
                    
                    var modalHtml = `<div class="cricket-modal">
                        <div class="cricket-modal-content">
                            <div class="cricket-modal-title">Select New Bowler</div>
                            <div class="cricket-modal-grid">${playerOptions || "<p>No other bowlers available.</p>"}</div>
                            <button class="cricket-btn cricket-btn-secondary cricket-btn-full btn-cancel-modal">Cancel</button>
                        </div>
                    </div>`;
                    $("body").append(modalHtml);

                    var self = this; 
                    $(".cricket-modal .cricket-modal-btn").off("click").on("click", function() {
                        self.currentMatch.current_bowler = $(this).data("player");
                        self.saveMatchState(function(success) {
                            if(success) {
                                self.closeModal();
                                self.renderMatch();
                            }
                        });
                    });
                },
                
                handleScoring: function(event) { 
                    var match = this.currentMatch; 
                    if (!match.current_batsman || !match.current_bowler) {
                        this.showNotification("Please select batsman and bowler", "error"); 
                        return;
                    }

                    var type = $(event.currentTarget).data("type"); 
                    var runs = parseInt($(event.currentTarget).data("runs")) || 0; 
                    var self = this; 
                    
                    if (type === "noball") {
                        self.showNoBallModal();
                        return;
                    } else if (type === "wide") {
                        self.addScore(1, false, true, false, 0); 
                    } else if (type === "wicket") {
                        self.addScore(0, true, false, false, 0);
                    } else { 
                        self.addScore(runs, false, false, false, runs);
                    }
                },
                
                showNoBallModal: function() {
                    var modalHtml = `<div class="cricket-modal">
                        <div class="cricket-modal-content">
                            <div class="cricket-modal-title">No Ball + Runs Off Bat</div>
                            <div class="cricket-modal-grid">
                                <div class="cricket-modal-btn" data-nb-runs="0">NB + 0</div>
                                <div class="cricket-modal-btn" data-nb-runs="1">NB + 1</div>
                                <div class="cricket-modal-btn" data-nb-runs="2">NB + 2</div>
                                <div class="cricket-modal-btn" data-nb-runs="3">NB + 3</div>
                                <div class="cricket-modal-btn" data-nb-runs="4">NB + 4</div>
                                <div class="cricket-modal-btn" data-nb-runs="6">NB + 6</div>
                            </div>
                            <button class="cricket-btn cricket-btn-secondary cricket-btn-full btn-cancel-modal">Cancel</button>
                        </div>
                    </div>`;
                    $("body").append(modalHtml);
                    
                    var self = this; 
                    $(".cricket-modal .cricket-modal-btn[data-nb-runs]").off("click").on("click", function() {
                        var runsOffBat = parseInt($(this).data("nb-runs"));
                        var totalRuns = runsOffBat + 1; 
                        
                        self.closeModal();
                        self.addScore(totalRuns, false, false, true, runsOffBat); 
                    });
                },
                
                closeModal: function() {
                    $(".cricket-modal").remove();
                },

                showNotification: function(message, type = "info") {
                    var bgColor = type === "error" ? "var(--c9-danger)" : 
                                  type === "success" ? "var(--c9-success)" : "var(--c9-info)";
                    
                    var notification = $(`<div style="position:fixed;top:20px;right:20px;background:${bgColor};color:white;padding:15px 25px;border-radius:10px;box-shadow:0 4px 15px rgba(0,0,0,0.2);font-weight:600;z-index:10001;animation:slideInRight 0.3s ease;">${message}</div>`);
                    
                    $("body").append(notification);
                    
                    setTimeout(function() {
                        notification.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                },
                
                showConfirm: function(message, onConfirm, onCancel) {
                    var modalHtml = `<div class="cricket-modal">
                        <div class="cricket-modal-content">
                            <div class="cricket-modal-title">Confirm</div>
                            <div class="cricket-modal-message">${message}</div>
                            <div class="cricket-modal-actions">
                                <button class="cricket-btn cricket-btn-secondary cricket-btn-full btn-cancel-confirm">Cancel</button>
                                <button class="cricket-btn cricket-btn-full btn-confirm-action">Confirm</button>
                            </div>
                        </div>
                    </div>`;
                    $("body").append(modalHtml);
                    
                    $(".btn-confirm-action").off("click").on("click", function() {
                        $(".cricket-modal").remove();
                        if (onConfirm) onConfirm();
                    });
                    
                    $(".btn-cancel-confirm").off("click").on("click", function() {
                        $(".cricket-modal").remove();
                        if (onCancel) onCancel();
                    });
                },
                
                addScore: function(totalRunsScored, isWicket, isWide, isNoBall, runsOffBat) {
                    var match = this.currentMatch; 
                    var battingTeamId = parseInt(match.current_innings);
                    var bowlingTeamId = battingTeamId == 1 ? 2 : 1;
                    var currentBatsmanName = match.current_batsman;
                    var currentBowlerName = match.current_bowler;

                    var battingTeamStats = battingTeamId == 1 ? this._parsePlayerStats(match.team1_player_stats) : this._parsePlayerStats(match.team2_player_stats);
                    var bowlingTeamStats = bowlingTeamId == 1 ? this._parsePlayerStats(match.team1_player_stats) : this._parsePlayerStats(match.team2_player_stats);
                    
                    if (!battingTeamStats[currentBatsmanName]) battingTeamStats[currentBatsmanName] = this._getPlayerStats([currentBatsmanName], battingTeamStats)[currentBatsmanName];
                    if (!bowlingTeamStats[currentBowlerName]) bowlingTeamStats[currentBowlerName] = this._getPlayerStats([currentBowlerName], bowlingTeamStats)[currentBowlerName];

                    var batsmanStats = battingTeamStats[currentBatsmanName];
                    var bowlerStats = bowlingTeamStats[currentBowlerName];

                    this.lastAction = {
                        description: `${totalRunsScored} runs, Wicket: ${isWicket}, Wide: ${isWide}, NoBall: ${isNoBall}`,
                        prevState: JSON.parse(JSON.stringify(match)) 
                    };
                    
                    if (battingTeamId == 1) {
                        match.team1_score = parseInt(match.team1_score) + totalRunsScored;
                        if (isWicket) match.team1_wickets = parseInt(match.team1_wickets) + 1;
                    } else {
                        match.team2_score = parseInt(match.team2_score) + totalRunsScored;
                        if (isWicket) match.team2_wickets = parseInt(match.team2_wickets) + 1;
                    }

                    if (!isWide) { 
                        batsmanStats.balls_faced += 1;
                    }
                    batsmanStats.runs += runsOffBat;
                    if (runsOffBat === 4) batsmanStats.fours += 1;
                    if (runsOffBat === 6) batsmanStats.sixes += 1;
                    if (isWicket) {
                        batsmanStats.is_out = true;
                        batsmanStats.out_details = 'out';
                    }

                    if (!isWide && !isNoBall) { 
                        bowlerStats.balls_bowled += 1;
                    }
                    bowlerStats.runs_conceded += totalRunsScored;
                    if (isWicket) {
                         bowlerStats.wickets_taken += 1;
                    }

                    if (!match.ball_by_ball_log) match.ball_by_ball_log = [];
                    match.ball_by_ball_log.push({
                        over: parseInt(match.current_over),
                        ball_in_over: parseInt(match.current_ball) + 1, 
                        innings: battingTeamId,
                        batsman: currentBatsmanName,
                        bowler: currentBowlerName,
                        runs_scored: totalRunsScored, 
                        runs_off_bat: runsOffBat,
                        is_wicket: isWicket,
                        is_wide: isWide,
                        is_noball: isNoBall,
                        timestamp: new Date().toISOString()
                    });

                    if (!isWide && !isNoBall) {
                        match.current_ball = parseInt(match.current_ball) + 1;
                    }
                    
                    var overCompleted = false;
                    if (match.current_ball >= 6) { 
                        match.current_over = parseInt(match.current_over) + 1;
                        match.current_ball = 0;
                        overCompleted = true;
                        if (bowlerStats) bowlerStats.overs_bowled = Math.floor(bowlerStats.balls_bowled / 6); 
                    }

                    var self = this; 
                    
                    // Trigger auto-save
                    self.autoSave();
                    
                    if (isWicket) {
                        let currentWickets = battingTeamId == 1 ? match.team1_wickets : match.team2_wickets;
                        let playersInTeam = this._parsePlayerList(battingTeamId == 1 ? match.team1_players : match.team2_players).length;
                        if (currentWickets >= playersInTeam -1 ) { 
                             if (battingTeamId == 1) { 
                                self.saveMatchState(() => self.showInningsBreak()); return;
                            } else { 
                                match.match_status = "completed";
                                self.saveMatchState(() => self.showMatchResult()); return;
                            }
                        }
                    }
                    if (battingTeamId == 2 && parseInt(match.team2_score) > parseInt(match.team1_score)) {
                        match.match_status = "completed";
                        self.saveMatchState(() => self.showMatchResult()); return;
                    }

                    if (parseInt(match.current_over) >= parseInt(match.overs_per_team) && overCompleted) { 
                        if (battingTeamId == 1) {
                            self.saveMatchState(() => self.showInningsBreak()); return;
                        } else { 
                            match.match_status = "completed";
                            self.saveMatchState(() => self.showMatchResult()); return;
                        }
                    }
                    
                    if (isWicket) {
                        this.saveMatchState(function(success) {
                           if(success) {
                                self.renderMatch(); 
                                setTimeout(function() { self.changeBatsman(); }, 100); 
                           }
                        });
                        return;
                    }
                    
                    if (overCompleted) {
                         this.saveMatchState(function(success) {
                            if(success) {
                                self.renderMatch(); 
                                setTimeout(function() {
                                    self.showConfirm("Over completed! Change bowler?", 
                                        function() { self.changeBowler(); },
                                        function() { self.renderMatch(); } 
                                    );
                                }, 100);
                            }
                         });
                         return;
                    }
                    
                    this.renderMatch();
                },

                undoLastAction: function() {
                    if (!this.lastAction || !this.lastAction.prevState) { 
                        this.showNotification("No action to undo", "error"); 
                        return;
                    }
                    
                    var self = this; 
                    this.showConfirm("Undo last action: " + this.lastAction.description + "?", function() { 
                        self.currentMatch = JSON.parse(JSON.stringify(self.lastAction.prevState));
                        
                        self.currentMatch.team1_player_stats = self._parsePlayerStats(self.currentMatch.team1_player_stats);
                        self.currentMatch.team2_player_stats = self._parsePlayerStats(self.currentMatch.team2_player_stats);
                        self.currentMatch.ball_by_ball_log = self._parseBallLog(self.currentMatch.ball_by_ball_log);

                        self.lastAction = null; 
                        self.saveMatchState(function(success) {
                            if(success) self.renderMatch();
                        });
                    });
                },
                
                showInningsBreak: function() {
                    var match = this.currentMatch; 
                    var firstInningsTeamName = match.team1_name;
                    var firstInningsPlayers = this._parsePlayerList(match.team1_players);
                    var firstInningsStats = this._parsePlayerStats(match.team1_player_stats);
                    
                    var secondInningsTeamName = match.team2_name;
                    var secondInningsPlayers = this._parsePlayerList(match.team2_players);
                    var secondInningsStats = this._parsePlayerStats(match.team2_player_stats);

                    var battingCardHtml = this._generateBattingScorecardHtml(firstInningsTeamName, firstInningsPlayers, firstInningsStats, null);
                    var bowlingCardHtml = this._generateBowlingScorecardHtml(secondInningsTeamName, secondInningsPlayers, secondInningsStats, null);

                    var target = parseInt(match.team1_score) + 1;
                    
                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">Innings Break</div>
                            <div class="cricket-subtitle">First innings completed</div>
                        </div>
                        <div class="cricket-content">
                            <div class="cricket-selection-screen">
                                <h3 style="color:var(--c9-primary);">${firstInningsTeamName} scored ${match.team1_score}/${match.team1_wickets}</h3>
                                <div class="cricket-detailed-scorecard" style="text-align:left; max-width: 700px; margin: 25px auto;">
                                    ${battingCardHtml}
                                    ${bowlingCardHtml}
                                </div>
                                <p style="margin:25px 0; font-size: 20px; font-weight: 600; color:var(--c9-dark);">
                                    🎯 ${secondInningsTeamName} needs <span style="color:var(--c9-primary);">${target}</span> runs to win in ${match.overs_per_team} overs
                                </p>
                                <div class="cricket-actions">
                                    <button class="cricket-btn btn-start-second-innings">Start Second Innings</button>
                                </div>
                            </div>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                    
                    var self = this; 
                    $(".btn-start-second-innings").off("click").on("click", function() {
                        self.currentMatch.current_innings = 2; 
                        self.currentMatch.current_over = 0;    
                        self.currentMatch.current_ball = 0;    
                        self.currentMatch.current_batsman = null; 
                        self.currentMatch.current_bowler = null;
                        self.saveMatchState(function(success) {
                            if(success) self.showPlayerSelection(true); 
                        });
                    });
                },
                                
                showMatchResult: function() {
                    var match = this.currentMatch; 
                    var team1Score = parseInt(match.team1_score);
                    var team2Score = parseInt(match.team2_score);
                    var winner = "";
                    var margin = "";
                    
                    if (team1Score > team2Score) {
                        winner = match.team1_name;
                        margin = (team1Score - team2Score) + " runs";
                    } else if (team2Score > team1Score) {
                        winner = match.team2_name;
                        let team2PlayersCount = this._parsePlayerList(match.team2_players).length; 
                        let wicketsLeft = (team2PlayersCount - 1) - parseInt(match.team2_wickets);
                        wicketsLeft = Math.max(0, wicketsLeft); 
                        margin = wicketsLeft + " wicket" + (wicketsLeft !== 1 ? "s" : "");
                    } else {
                        winner = "Match Tied";
                        margin = "";
                    }

                    const team1Players = this._parsePlayerList(match.team1_players);
                    const team1Stats = this._parsePlayerStats(match.team1_player_stats);
                    const team2Players = this._parsePlayerList(match.team2_players);
                    const team2Stats = this._parsePlayerStats(match.team2_player_stats);

                    let team1BattingCardHtml = this._generateBattingScorecardHtml(match.team1_name, team1Players, team1Stats, null);
                    let team2BowlingCardHtml = this._generateBowlingScorecardHtml(match.team2_name, team2Players, team2Stats, null);

                    let team2BattingCardHtml = this._generateBattingScorecardHtml(match.team2_name, team2Players, team2Stats, null);
                    let team1BowlingCardHtml = this._generateBowlingScorecardHtml(match.team1_name, team1Players, team1Stats, null);

                    
                    var html = `<div class="cricket-container">
                        <div class="cricket-header">
                            <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" class="cricket-logo">
                            <div class="cricket-title">Match Result</div>
                            <div class="cricket-subtitle">Match completed</div>
                        </div>
                        <div class="cricket-content">
                            <div class="cricket-selection-screen">
                                <h2 style="color:var(--c9-primary);margin-bottom:25px;">🏆 ${winner + (winner !== "Match Tied" ? " Won!" : "")}</h2>
                                ${margin ? `<p style="font-size:20px;margin-bottom:25px;font-weight:600;">by ${margin}</p>` : ""}
                                <div style="background:var(--c9-light-gray);padding:25px;border-radius:15px;margin-bottom:30px;border:2px solid var(--c9-border);">
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:25px;">
                                        <div style="text-align:center;">
                                            <h4 style="color:var(--c9-dark);margin-bottom:10px;">${match.team1_name}</h4>
                                            <p style="font-size:32px;font-weight:800;color:var(--c9-primary);">${match.team1_score}/${match.team1_wickets}</p>
                                            <p style="color:var(--c9-gray);font-size:14px;margin-top:5px;">${match.overs_per_team} overs</p>
                                        </div>
                                        <div style="text-align:center;">
                                            <h4 style="color:var(--c9-dark);margin-bottom:10px;">${match.team2_name}</h4>
                                            <p style="font-size:32px;font-weight:800;color:var(--c9-primary);">${match.team2_score}/${match.team2_wickets}</p>
                                            <p style="color:var(--c9-gray);font-size:14px;margin-top:5px;">${match.overs_per_team} overs</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="cricket-detailed-scorecard" style="text-align:left; max-width: 700px; margin: 0 auto;">
                                    <h3 style="text-align:center;color:var(--c9-dark);margin-bottom:25px;padding-bottom:15px;border-bottom:3px solid var(--c9-primary);">
                                        Complete Scorecard
                                    </h3>
                                    ${team1BattingCardHtml}
                                    ${team2BowlingCardHtml}
                                    <hr style="margin: 30px 0;border:1px solid var(--c9-border);"/>
                                    ${team2BattingCardHtml}
                                    ${team1BowlingCardHtml}
                                </div>

                                <div class="cricket-actions" style="margin-top:30px;">
                                    <button class="cricket-btn btn-back">Back to Dashboard</button>
                                </div>
                            </div>
                        </div>
                        <div class="cricket-footer">
                            Made with <span class="cricket-footer-heart">❤️</span> for Cricket
                        </div>
                    </div>`;
                    $("#cloud9-cricket-app").html(html);
                },
                
                saveMatchState: function(callback, silent = false) {
                    if (!this.currentMatch || !this.currentMatch.id) { 
                        console.error("Cannot save match state: No current match ID.");
                        if (callback) callback(false); 
                        return;
                    }
                    let matchDataToSave = JSON.parse(JSON.stringify(this.currentMatch)); 
                    matchDataToSave.team1_player_stats = JSON.stringify(matchDataToSave.team1_player_stats || {});
                    matchDataToSave.team2_player_stats = JSON.stringify(matchDataToSave.team2_player_stats || {});
                    matchDataToSave.ball_by_ball_log = JSON.stringify(matchDataToSave.ball_by_ball_log || []);

                    var self = this; 
                    $.post(cloud9_cricket_ajax.url, {
                        action: "cloud9_cricket_action",
                        cricket_action: "update_match", 
                        match_id: self.currentMatch.id, 
                        match_data: JSON.stringify(matchDataToSave), 
                        nonce: cloud9_cricket_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            if (!silent) {
                                // Show a quick save indicator
                                var saveIndicator = $('<div style="position:fixed;bottom:20px;right:20px;background:var(--c9-success);color:white;padding:10px 20px;border-radius:8px;font-weight:600;opacity:0;transition:opacity 0.3s;">✓ Saved</div>');
                                $("body").append(saveIndicator);
                                saveIndicator.css("opacity", "1");
                                setTimeout(function() {
                                    saveIndicator.css("opacity", "0");
                                    setTimeout(function() {
                                        saveIndicator.remove();
                                    }, 300);
                                }, 1000);
                            }
                            if (callback) callback(true); 
                        } else {
                            if (!silent) {
                                self.showNotification("Failed to save match", "error"); 
                            }
                            if (callback) callback(false); 
                        }
                    }).fail(function() {
                        if (!silent) {
                            self.showNotification("Error saving match", "error"); 
                        }
                        if (callback) callback(false); 
                    });
                }
            };
            
            if ($("#cloud9-cricket-app").length) {
                Cloud9CricketApp.init();
            }
        });
        </script>
        <style>
        @keyframes slideInRight {
            from { transform: translateX(100px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        </style>
        <?php
    }
    
    public function render_shortcode() {
        if (get_option('require_login_for_cloud9_cricket', true) && !is_user_logged_in()) { 
            return '<div style="background:linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);padding:60px 20px;text-align:center;border-radius:20px;border:2px solid #e5e7eb;box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                        <img src="https://cloud9digital.in/wp-content/uploads/2024/11/Cloud-9-Logo-New.svg" alt="Cloud 9" style="height:60px;margin-bottom:20px;">
                        <h3 style="color:#1e293b;margin-bottom:20px;font-size:24px;">Please log in to use Cloud 9 Box Cricket Manager</h3>
                        <a href="' . wp_login_url(get_permalink()) . '" style="background:#F04A24;background:linear-gradient(135deg, #F04A24 0%, #D43918 100%);color:white;padding:14px 30px;border-radius:12px;text-decoration:none;font-weight:600;display:inline-block;box-shadow:0 4px 15px rgba(240, 74, 36, 0.3);transition:all 0.3s;" onmouseover="this.style.transform=\'translateY(-2px)\';this.style.boxShadow=\'0 6px 20px rgba(240, 74, 36, 0.4)\';" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 15px rgba(240, 74, 36, 0.3)\';">Login to Continue</a>
                    </div>';
        }
        
        return '<div class="cricket-app"><div id="cloud9-cricket-app"><div class="cricket-loading"><div class="cricket-spinner"></div><p>Loading Cloud 9 Cricket Manager...</p></div></div></div>';
    }
    
    public function handle_ajax() {
        check_ajax_referer('cloud9_cricket_nonce', 'nonce');
        
        $require_login = get_option('require_login_for_cloud9_cricket', true);
        if ($require_login && !is_user_logged_in()) {
            wp_send_json_error('Unauthorized: Login required.', 403);
            wp_die();
        }
        
        $action = isset($_POST['cricket_action']) ? sanitize_text_field($_POST['cricket_action']) : '';
        $user_id = get_current_user_id(); 
        
        global $wpdb;
        $table = $wpdb->prefix . 'cloud9_cricket_matches';
        
        switch ($action) {
            case 'create_match':
                $team1_players_json = isset($_POST['team1_players']) ? wp_unslash($_POST['team1_players']) : '[]';
                $team2_players_json = isset($_POST['team2_players']) ? wp_unslash($_POST['team2_players']) : '[]';
                $team1_player_stats_json = isset($_POST['team1_player_stats']) ? wp_unslash($_POST['team1_player_stats']) : '{}';
                $team2_player_stats_json = isset($_POST['team2_player_stats']) ? wp_unslash($_POST['team2_player_stats']) : '{}';

                $result = $wpdb->insert($table, array(
                    'user_id' => $user_id,
                    'match_name' => isset($_POST['match_name']) ? sanitize_text_field($_POST['match_name']) : 'New Match',
                    'match_location' => isset($_POST['match_location']) ? sanitize_text_field($_POST['match_location']) : '',
                    'batting_type' => isset($_POST['batting_type']) ? sanitize_text_field($_POST['batting_type']) : 'single',
                    'team1_name' => isset($_POST['team1_name']) ? sanitize_text_field($_POST['team1_name']) : 'Team 1',
                    'team2_name' => isset($_POST['team2_name']) ? sanitize_text_field($_POST['team2_name']) : 'Team 2',
                    'team1_players' => $team1_players_json,
                    'team2_players' => $team2_players_json,
                    'joker_player' => isset($_POST['joker_player']) ? sanitize_text_field($_POST['joker_player']) : '',
                    'players_per_team' => isset($_POST['players_per_team']) ? intval($_POST['players_per_team']) : 6,
                    'overs_per_team' => isset($_POST['overs_per_team']) ? intval($_POST['overs_per_team']) : 6,
                    'current_over' => 0, 
                    'current_ball' => 0, 
                    'team1_player_stats' => $team1_player_stats_json,
                    'team2_player_stats' => $team2_player_stats_json,
                    'ball_by_ball_log' => '[]' 
                ));
                
                if ($result) {
                    $match_id = $wpdb->insert_id;
                    $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $match_id));
                    if ($match) {
                        wp_send_json_success($match);
                    } else {
                        wp_send_json_error('Failed to retrieve created match.');
                    }
                } else {
                    wp_send_json_error('Failed to create match in database. DB Error: ' . $wpdb->last_error);
                }
                break;
                
            case 'get_matches':
                $query = $require_login ? $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC", $user_id) : "SELECT * FROM $table ORDER BY created_at DESC";
                $matches = $wpdb->get_results($query);
                wp_send_json_success($matches);
                break;
                
            case 'get_match':
                $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
                if (!$match_id) {
                     wp_send_json_error('Match ID not provided.'); break;
                }
                $query = $require_login ? $wpdb->prepare("SELECT * FROM $table WHERE id = %d AND user_id = %d", $match_id, $user_id) : $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $match_id);
                $match = $wpdb->get_row($query);

                if ($match) {
                    wp_send_json_success($match);
                } else {
                    wp_send_json_error('Match not found or access denied.');
                }
                break;
                
            case 'update_match':
                $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
                $match_data_json = isset($_POST['match_data']) ? wp_unslash($_POST['match_data']) : null;

                if (!$match_id || !$match_data_json) {
                    wp_send_json_error('Missing match ID or data for update.'); break;
                }
                
                $match_data = json_decode($match_data_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error('Invalid match data format: ' . json_last_error_msg()); break;
                }

                if ($require_login) {
                    $current_match_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $match_id));
                    if ($current_match_user_id != $user_id) {
                        wp_send_json_error('Permission denied to update this match.', 403);
                        wp_die();
                    }
                }
                
                $allowed_fields = array(
                    'team1_score', 'team1_wickets', 'team2_score', 'team2_wickets',
                    'current_innings', 'current_over', 'current_ball',
                    'current_batsman', 'current_bowler', 'match_status',
                    'team1_player_stats', 'team2_player_stats', 'ball_by_ball_log' 
                );
                $update_data = array();
                foreach($allowed_fields as $field) {
                    if (isset($match_data[$field])) {
                        if (is_string($match_data[$field])) {
                            if ($field === 'current_batsman' || $field === 'current_bowler' || $field === 'match_status') {
                                $update_data[$field] = sanitize_text_field($match_data[$field]);
                            } else {
                                $update_data[$field] = $match_data[$field]; 
                            }
                        } else { 
                            $update_data[$field] = intval($match_data[$field]);
                        }
                    }
                }
                
                if (empty($update_data)) {
                     wp_send_json_error('No valid data provided for update.'); break;
                }

                $result = $wpdb->update($table, $update_data, array('id' => $match_id));
                
                if ($result !== false) { 
                    wp_send_json_success('Match updated successfully.');
                } else {
                    wp_send_json_error('Failed to update match. DB Error: ' . $wpdb->last_error);
                }
                break;
                
            case 'delete_match':
                $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
                if (!$match_id) {
                    wp_send_json_error('Match ID not provided.'); break;
                }
                
                if ($require_login) {
                    $current_match_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $match_id));
                    if ($current_match_user_id != $user_id) {
                        wp_send_json_error('Permission denied to delete this match.', 403);
                        wp_die();
                    }
                }
                
                $result = $wpdb->delete($table, array('id' => $match_id));
                
                if ($result) {
                    wp_send_json_success('Match deleted successfully.');
                } else {
                    wp_send_json_error('Failed to delete match.');
                }
                break;
            
            default:
                wp_send_json_error('Invalid cricket action.');
                break;
        }
        
        wp_die(); 
    }
}

new Cloud9BoxCricketManager();

// Admin settings
add_action('admin_init', 'cloud9_cricket_manager_register_settings');
function cloud9_cricket_manager_register_settings() {
    register_setting('general', 'require_login_for_cloud9_cricket', 'sanitize_text_field');
    add_settings_field(
        'require_login_for_cloud9_cricket',
        'Require Login for Cloud 9 Cricket App',
        'cloud9_cricket_manager_settings_field_html',
        'general',
        'default',
        array( 'label_for' => 'require_login_for_cloud9_cricket' )
    );
}

function cloud9_cricket_manager_settings_field_html() {
    $value = get_option('require_login_for_cloud9_cricket', true); 
    echo '<input type="checkbox" id="require_login_for_cloud9_cricket" name="require_login_for_cloud9_cricket" value="1"' . checked(1, $value, false) . '/>';
    echo '<label for="require_login_for_cloud9_cricket"> Users must be logged in to access the Cloud 9 Box Cricket Manager.</label>';
}

?>