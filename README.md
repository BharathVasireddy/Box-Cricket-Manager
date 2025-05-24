# Cloud 9 Box Cricket Manager

**A modern, mobile-first box cricket scoring system for WordPress with real-time save functionality.**

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/your-plugin-slug.svg?style=flat-square)](https://wordpress.org/plugins/your-plugin-slug/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/your-plugin-slug.svg?style=flat-square)](https://wordpress.org/plugins/your-plugin-slug/)
_Note: Replace `your-plugin-slug` with the actual slug if/when published on WordPress.org, or remove these badges if not applicable._

---

Cloud 9 Box Cricket Manager is a feature-rich WordPress plugin designed to make scoring box cricket matches easy, intuitive, and accessible on any device. It features a sleek, modern interface, real-time data saving, and comprehensive match management capabilities.

![Screenshot Placeholder - e.g., Dashboard or Scoring Interface](https://via.placeholder.com/800x400.png?text=Cloud+9+Cricket+App+Interface)
*(Replace with actual screenshots)*

## Features

*   **Mobile-First Design:** Fully responsive interface, perfect for scoring on the go.
*   **Real-Time Auto-Save:** Match progress is saved automatically every few seconds, preventing data loss.
*   **User-Friendly Interface:** Intuitive design for easy match setup and scoring.
*   **Comprehensive Match Setup:**
    *   Custom match names and locations.
    *   Single or Double batting styles.
    *   Configurable players per team (2-8).
    *   Configurable overs per team (1-50).
    *   Team name and player name entry.
    *   Optional Joker Player (can play for both teams).
*   **Interactive Scoring:**
    *   Simple tap-based scoring for runs (0-6), wickets, wides, and no-balls.
    *   Handles no-ball runs (NB + runs off bat).
    *   Ball-by-ball tracker for the current over.
    *   Undo last action.
*   **Dynamic Player Management:**
    *   Select opening batsman and bowler.
    *   Change batsman (on wicket or retirement).
    *   Change bowler (at the end of an over).
*   **Detailed Scorecards:**
    *   Live team scores and wickets.
    *   Current batsman and bowler statistics.
    *   Required Run Rate (RRR) and target display for second innings.
    *   Full batting scorecard for each innings (runs, balls, 4s, 6s, SR, status).
    *   Full bowling scorecard for each innings (overs, maidens, runs, wickets, economy).
    *   Fall of Wickets (FOW) display.
*   **Match Management:**
    *   Dashboard to view all matches.
    *   Resume active matches.
    *   View completed match summaries and detailed scorecards.
    *   Delete matches.
*   **Innings Break & Match Result Screens:** Clear summaries at critical match stages.
*   **User Authentication:**
    *   Optionally require users to be logged into WordPress to use the app.
    *   Admin setting to toggle login requirement (General Settings).
*   **AJAX Powered:** Smooth and fast operations without page reloads.
*   **Custom Database Table:** Stores all match data efficiently (`wp_cloud9_cricket_matches`).

## Installation

1.  **Download:**
    *   Download the latest release ZIP file from the [GitHub Releases](https://github.com/your-username/cloud9-box-cricket-manager/releases) page.
    *   OR, clone the repository: `git clone https://github.com/your-username/cloud9-box-cricket-manager.git`
2.  **Upload to WordPress:**
    *   If you downloaded the ZIP:
        *   Log in to your WordPress admin panel.
        *   Go to `Plugins` > `Add New`.
        *   Click `Upload Plugin`.
        *   Choose the downloaded ZIP file and click `Install Now`.
    *   If you cloned the repository:
        *   Compress the `cloud9-box-cricket-manager` folder into a ZIP file.
        *   Then follow the steps above to upload the ZIP.
        *   Alternatively, upload the `cloud9-box-cricket-manager` folder directly to your `wp-content/plugins/` directory via FTP.
3.  **Activate:**
    *   Go to `Plugins` in your WordPress admin panel.
    *   Find "Cloud 9 Box Cricket Manager" and click `Activate`.

The plugin will automatically create the necessary database table (`wp_cloud9_cricket_matches`) upon activation.

## Usage

1.  **Place the Shortcode:**
    *   Create a new Page or Post in WordPress (e.g., "Cricket Scoring").
    *   Add the following shortcode to the content area:
        ```
        [cloud9_cricket]
        ```
    *   Publish the Page/Post.
2.  **Access the App:**
    *   Navigate to the Page/Post you created. The Cloud 9 Box Cricket Manager app will load.
3.  **Admin Setting (Login Requirement):**
    *   By default, users must be logged in to WordPress to use the app.
    *   To change this, go to `Settings` > `General` in your WordPress admin.
    *   Find the "Require Login for Cloud 9 Cricket App" option and check/uncheck it as desired.
    *   Save changes.

### App Workflow:

1.  **Dashboard:**
    *   View a list of existing matches (in-progress or completed).
    *   Click "🚀 Start New Match" to create a new one.
    *   Click on an existing match to resume or view its details.
    *   Delete matches using the 🗑️ icon (confirmation required).
2.  **New Match Setup:**
    *   Fill in match details (name, location, teams, players, overs, etc.).
    *   Player names are required.
3.  **Team Selection (Toss):**
    *   After creating a match, choose which team will bat first.
4.  **Player Selection:**
    *   Select the opening batsman for the batting team and the opening bowler for the fielding team.
5.  **Scoring:**
    *   The main scoring interface will appear.
    *   Use the buttons to record runs, wickets, wides, or no-balls.
    *   The scorecard, ball tracker, and player stats update in real-time.
    *   Use "Change Batsman" or "Change Bowler" buttons as needed.
    *   Use "Undo" to revert the last scoring action.
6.  **Innings Break:**
    *   After the first innings is complete (overs bowled or all out), an innings break screen will show a summary and the target for the next team.
7.  **Match Result:**
    *   Once the match is complete, a result screen displays the winner, margin, and full scorecards for both innings.
8.  **Auto-Save:**
    *   The app automatically saves the match state every few seconds to the database. A small "✓ Saved" indicator will appear briefly.

## Technical Details

*   **Database Table:** `wp_cloud9_cricket_matches` stores all match information, including:
    *   Match settings (teams, players, overs)
    *   Live scores and wickets
    *   Current batsman, bowler, over, ball
    *   Player statistics (batting and bowling)
    *   Ball-by-ball log for detailed reconstruction
    *   Match status (active, completed)
*   **AJAX Actions (handled via `admin-ajax.php`):**
    *   `create_match`: Saves new match setup.
    *   `get_matches`: Retrieves list of all matches for the dashboard.
    *   `get_match`: Retrieves full data for a specific match.
    *   `update_match`: Saves ongoing match state (scores, current players, stats, ball log).
    *   `delete_match`: Removes a match from the database.
*   **Frontend:**
    *   Built with jQuery for DOM manipulation and AJAX.
    *   Extensive custom CSS for styling and responsiveness.
    *   The main application logic resides in the `Cloud9CricketApp` JavaScript object.

## Key JavaScript Functionality (`Cloud9CricketApp`)

*   `init()`: Initializes the app and loads the dashboard.
*   `bindEvents()`: Sets up all event listeners for user interactions.
*   `autoSave()`: Periodically saves the current match state.
*   `loadDashboard()`, `loadMatches()`, `renderMatches()`: Handles the main match listing.
*   `showNewMatchForm()`, `saveNewMatch()`: Manages new match creation.
*   `showTeamSelection()`, `confirmTeamSelection()`: Handles who bats first.
*   `showPlayerSelection()`, `confirmStartMatch()`: Handles selection of initial batsman/bowler.
*   `loadMatch()`, `renderMatch()`: Loads and displays the scoring interface for a specific match.
*   `_generateBattingScorecardHtml()`, `_generateBowlingScorecardHtml()`, `_getFallOfWickets()`: Generates detailed scorecard tables.
*   `handleScoring()`, `addScore()`: Processes scoring events and updates match data.
*   `showNoBallModal()`: Handles runs scored off a no-ball.
*   `changeBatsman()`, `changeBowler()`: Manages player changes.
*   `undoLastAction()`: Reverts the previous scoring input.
*   `showInningsBreak()`, `showMatchResult()`: Displays relevant information at innings end or match completion.
*   `saveMatchState()`: Core function to persist match data to the backend via AJAX.
*   Utility functions for modals, notifications, parsing player lists/stats.

## Future Enhancements / Roadmap

*   [ ] Player profiles and career statistics across matches.
*   [ ] Tournament management features.
*   [ ] Advanced statistics (e.g., partnership records, graphs).
*   [ ] Option to export match data (CSV/PDF).
*   [ ] Public shareable links for match scorecards.
*   [ ] Dark mode / Theme options.
*   [ ] More granular wicket-taking details (e.g., bowled, caught by).

## Contributing

Contributions are welcome! If you'd like to contribute, please:

1.  Fork the repository.
2.  Create a new branch (`git checkout -b feature/your-feature-name`).
3.  Make your changes.
4.  Commit your changes (`git commit -m 'Add some feature'`).
5.  Push to the branch (`git push origin feature/your-feature-name`).
6.  Open a Pull Request.

Please ensure your code follows WordPress coding standards.

## License

This plugin is licensed under the GPLv2 or later.
See the `LICENSE.txt` file for more details (standard WordPress plugin license).

## Author

Developed by **Cloud 9 Digital**.
*   Website: [cloud9digital.in](https://cloud9digital.in)
