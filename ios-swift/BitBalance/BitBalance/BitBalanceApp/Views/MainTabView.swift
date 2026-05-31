import SwiftUI

struct MainTabView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var showAuthFromGuest = false

    var body: some View {
        ZStack(alignment: .top) {
            TabView {
                DashboardView()
                    .tabItem { Label("Dashboard", systemImage: "chart.bar.fill") }

                LogFoodView()
                    .tabItem { Label("Log", systemImage: "plus.circle.fill") }

                IntakeHistoryView()
                    .tabItem { Label("History", systemImage: "clock.fill") }

                AIChatView()
                    .tabItem { Label("AI Coach", systemImage: "brain.head.profile") }

                SocialView()
                    .tabItem { Label("Social", systemImage: "person.3.fill") }

                ProfileView()
                    .tabItem { Label("Settings", systemImage: "gearshape.fill") }
            }
            .tint(BBColors.primary)

            // Guest mode notice banner
            if session.isGuest {
                GuestBanner {
                    showAuthFromGuest = true
                }
                .transition(.move(edge: .top).combined(with: .opacity))
                .zIndex(1)
            }
        }
        .sheet(isPresented: $showAuthFromGuest) {
            AuthEntryView()
                .onDisappear {
                    // If user signed in from guest prompt, exit guest mode
                    if session.user != nil {
                        session.exitGuestMode()
                    }
                }
        }
        .animation(.easeInOut(duration: 0.25), value: session.isGuest)
    }
}

// MARK: - Guest banner

private struct GuestBanner: View {
    let onSignUp: () -> Void

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: "person.crop.circle.badge.questionmark")
                .font(.system(size: 16, weight: .bold))
                .foregroundColor(BBColors.accent)

            Text("Browsing as guest — data won't be saved")
                .font(.system(size: 13, weight: .bold))
                .foregroundColor(BBColors.text)
                .lineLimit(1)
                .minimumScaleFactor(0.8)

            Spacer()

            Button(action: onSignUp) {
                Text("Sign Up")
                    .font(.system(size: 13, weight: .heavy))
                    .foregroundColor(.white)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(BBColors.primary)
                    .cornerRadius(BBRadius.pill)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .background(
            BBColors.surface
                .overlay(
                    RoundedRectangle(cornerRadius: 0)
                        .stroke(BBColors.accent.opacity(0.3), lineWidth: 1)
                )
        )
        .shadow(color: .black.opacity(0.08), radius: 6, x: 0, y: 3)
    }
}

// MARK: - ==========================================
// MARK: SOCIAL VIEW (Friends, Search, Leaderboard)
// MARK: - ==========================================
struct SocialView: View {
    @EnvironmentObject private var session: SessionStore

    @State private var selectedTab = 0 // 0 = Leaderboard, 1 = Friends
    @State private var leaderboardPeriod = "weekly" // "weekly" or "all_time"
    @State private var searchUsername = ""
    @State private var searchResults: [SearchUserResult] = []
    
    @State private var friends: [FriendItem] = []
    @State private var pendingIn: [PendingFriendRequest] = []
    @State private var pendingOut: [PendingFriendRequest] = []
    @State private var leaders: [LeaderboardEntry] = []
    
    @State private var isSearching = false
    @State private var message: String? = nil
    @State private var messageIsError = false
    
    @FocusState private var isSearchFocused: Bool

    // Grid Layout for Friends Cards
    private let columns = [
        GridItem(.flexible(), spacing: 14),
        GridItem(.flexible(), spacing: 14)
    ]

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                VStack(spacing: 0) {
                    // Custom Duolingo-styled Segment Switcher
                    HStack(spacing: 0) {
                        tabButton(title: "🏆 Leaderboard", index: 0)
                        tabButton(title: "👥 Friends", index: 1)
                    }
                    .padding(6)
                    .background(BBColors.surfaceAlt)
                    .cornerRadius(BBRadius.lg)
                    .overlay(
                        RoundedRectangle(cornerRadius: BBRadius.lg)
                            .stroke(BBColors.border, lineWidth: 2)
                    )
                    .padding(.horizontal, 20)
                    .padding(.vertical, 12)
                    
                    ScrollView {
                        VStack(alignment: .leading, spacing: 20) {
                            
                            // 1. Premium Bento Hero Header Section
                            bentoHeroSection
                            
                            // Active Status Banners
                            if let message {
                                HStack(spacing: 10) {
                                    Image(systemName: messageIsError ? "exclamationmark.triangle.fill" : "checkmark.circle.fill")
                                        .font(.system(size: 16, weight: .bold))
                                    Text(message)
                                        .font(.system(size: 14, weight: .bold))
                                }
                                .frame(maxWidth: .infinity, alignment: .leading)
                                .bbAlert(isSuccess: !messageIsError)
                                .transition(.opacity.combined(with: .scale))
                                .padding(.horizontal, 20)
                            }
                            
                            if selectedTab == 0 {
                                // 🏆 LEADERBOARD PANEL
                                leaderboardView
                            } else {
                                // 👥 FRIENDS & MUTATIONS PANEL
                                friendsView
                            }
                        }
                        .padding(.bottom, 24)
                    }
                    .refreshable {
                        await loadSocialData()
                    }
                }
            }
            .navigationTitle("Social Hub")
            .task {
                await loadSocialData()
            }
        }
    }
    
    // MARK: - Tab Button Builder
    private func tabButton(title: String, index: Int) -> some View {
        Button {
            withAnimation(.interactiveSpring(response: 0.25, dampingFraction: 0.85, blendDuration: 0)) {
                selectedTab = index
            }
        } label: {
            Text(title)
                .font(.system(size: 14, weight: .black))
                .foregroundColor(selectedTab == index ? .white : BBColors.textSecondary)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 10)
                .background(selectedTab == index ? BBColors.primary : Color.clear)
                .cornerRadius(BBRadius.md)
                .background(
                    RoundedRectangle(cornerRadius: BBRadius.md)
                        .fill(selectedTab == index ? BBColors.primaryHover : Color.clear)
                        .offset(y: selectedTab == index ? 2 : 0)
                )
                .offset(y: selectedTab == index ? -2 : 0)
        }
    }

    // MARK: - Bento Hero Section Component
    private var bentoHeroSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 8) {
                Text("👥 SOCIAL HUB")
                    .font(.system(size: 10, weight: .black))
                    .foregroundColor(BBColors.primary)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 4)
                    .background(BBColors.primary.opacity(0.12))
                    .cornerRadius(BBRadius.pill)
                
                Spacer()
            }
            
            VStack(alignment: .leading, spacing: 6) {
                Text("Social Hub")
                    .font(.system(size: 24, weight: .heavy))
                    .foregroundColor(BBColors.text)
                
                Text("Connect with friends, track streaks, and earn XP together!")
                    .font(.system(size: 13, weight: .bold))
                    .foregroundColor(BBColors.textSecondary)
                    .lineLimit(2)
            }
            
            // Bento Metrics Cards Row
            HStack(spacing: 12) {
                bentoMetricCard(emoji: "👥", label: "Friends", value: "\(friends.count)")
                bentoMetricCard(emoji: "🏆", label: "Rank", value: myLeaderboardRank)
                bentoMetricCard(emoji: "🔥", label: "Streak", value: "\(myStreak) d")
            }
            .padding(.top, 4)
        }
        .bbCard()
        .padding(.horizontal, 20)
    }

    private func bentoMetricCard(emoji: String, label: String, value: String) -> some View {
        VStack(alignment: .center, spacing: 4) {
            Text(emoji)
                .font(.system(size: 20))
            Text(label.uppercased())
                .font(.system(size: 9, weight: .black))
                .foregroundColor(BBColors.textSecondary)
                .tracking(0.5)
            Text(value)
                .font(.system(size: 18, weight: .black))
                .foregroundColor(BBColors.text)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 12)
        .padding(.horizontal, 8)
        .background(BBColors.surfaceAlt)
        .cornerRadius(BBRadius.md)
        .overlay(
            RoundedRectangle(cornerRadius: BBRadius.md)
                .stroke(BBColors.border, lineWidth: 1.5)
        )
    }

    // Dynamic Rank Resolution
    private var myLeaderboardRank: String {
        if let me = leaders.first(where: { $0.isCurrentUser }) {
            return "#\(me.rank)"
        }
        return "-"
    }

    // Dynamic Streak Resolution
    private var myStreak: Int {
        if let me = leaders.first(where: { $0.isCurrentUser }) {
            return me.loggingStreak
        }
        return session.user != nil ? 0 : 0
    }
    
    // MARK: - Leaderboard View Component
    private var leaderboardView: some View {
        VStack(spacing: 16) {
            // Weekly vs All Time Picker
            HStack(spacing: 12) {
                periodButton(title: "Weekly XP", period: "weekly")
                periodButton(title: "All Time XP", period: "all_time")
                
                Spacer()
            }
            .padding(.horizontal, 20)
            
            if leaders.isEmpty {
                VStack(spacing: 16) {
                    Text("🏜️")
                        .font(.system(size: 48))
                    Text("No leaderboard stats found yet.")
                        .font(.system(size: 15, weight: .bold))
                        .foregroundColor(BBColors.textSecondary)
                    Text("Log meals to earn XP and rank among friends!")
                        .font(.system(size: 13, weight: .bold))
                        .foregroundColor(BBColors.textMuted)
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity)
                .bbCard()
                .padding(.horizontal, 20)
                .padding(.top, 20)
            } else {
                VStack(spacing: 12) {
                    ForEach(leaders) { leader in
                        let cardColor = leader.isCurrentUser ? BBColors.primary.opacity(0.08) : BBColors.surface
                        let borderColor = leader.isCurrentUser ? BBColors.primary : rankBorderColor(leader.rank)
                        let borderSubtleColor = leader.isCurrentUser ? BBColors.primaryHover : rankShadowColor(leader.rank)
                        
                        HStack(spacing: 14) {
                            // Rank Badge
                            ZStack {
                                Circle()
                                    .fill(rankBadgeColor(leader.rank))
                                    .frame(width: 34, height: 34)
                                    .overlay(
                                        Circle()
                                            .stroke(rankBorderColor(leader.rank), lineWidth: 1.5)
                                    )
                                
                                if leader.rank == 1 {
                                    Text("👑")
                                        .font(.system(size: 16))
                                        .offset(y: -14)
                                }
                                
                                Text(String(leader.rank))
                                    .font(.system(size: 13, weight: .black))
                                    .foregroundColor(rankTextColor(leader.rank))
                            }
                            
                            // double-bordered Avatar with Bottom-Right Level Badge
                            ZStack {
                                Circle()
                                    .fill(BBColors.accent.opacity(0.12))
                                    .frame(width: 48, height: 48)
                                    .overlay(
                                        Circle()
                                            .stroke(BBColors.accent, lineWidth: 2)
                                    )
                                Text(String(leader.userName.prefix(1).uppercased()))
                                    .font(.system(size: 18, weight: .black))
                                    .foregroundColor(BBColors.accent)
                            }
                            .overlay(
                                ZStack {
                                    Circle()
                                        .fill(BBColors.primary)
                                        .frame(width: 22, height: 22)
                                        .overlay(
                                            Circle()
                                                .stroke(Color.white, lineWidth: 2)
                                        )
                                    Text("\(leader.currentLevel)")
                                        .font(.system(size: 8, weight: .black))
                                        .foregroundColor(.white)
                                }
                                .offset(x: 18, y: 18)
                            )
                            .padding(.trailing, 4)
                            
                            VStack(alignment: .leading, spacing: 3) {
                                HStack(spacing: 6) {
                                    Text(leader.userName.split(separator: "#").first.map(String.init) ?? leader.userName)
                                        .font(.system(size: 15, weight: .heavy))
                                        .foregroundColor(BBColors.text)
                                    
                                    if leader.isCurrentUser {
                                        Text("YOU")
                                            .font(.system(size: 8, weight: .black))
                                            .foregroundColor(.white)
                                            .padding(.horizontal, 6)
                                            .padding(.vertical, 2)
                                            .background(BBColors.primary)
                                            .cornerRadius(6)
                                    }
                                }
                                
                                HStack(spacing: 8) {
                                    if leader.loggingStreak > 0 {
                                        Text("🔥 \(leader.loggingStreak) streak")
                                            .font(.system(size: 11, weight: .bold))
                                            .foregroundColor(.orange)
                                    } else {
                                        Text("💤 inactive")
                                            .font(.system(size: 11, weight: .bold))
                                            .foregroundColor(BBColors.textMuted)
                                    }
                                }
                            }
                            
                            Spacer()
                            
                            // XP Score Box
                            VStack(alignment: .trailing, spacing: 2) {
                                Text("\(leader.scoreXp)")
                                    .font(.system(size: 18, weight: .black))
                                    .foregroundColor(BBColors.text)
                                Text(leaderboardPeriod == "weekly" ? "WEEKLY XP" : "TOTAL XP")
                                    .font(.system(size: 8, weight: .black))
                                    .foregroundColor(BBColors.textSecondary)
                                    .tracking(0.5)
                            }
                        }
                        .padding(14)
                        .background(cardColor)
                        .cornerRadius(BBRadius.lg)
                        .overlay(
                            RoundedRectangle(cornerRadius: BBRadius.lg)
                                .stroke(borderColor, lineWidth: 2)
                        )
                        .background(
                            RoundedRectangle(cornerRadius: BBRadius.lg)
                                .fill(borderSubtleColor)
                                .offset(y: 6)
                        )
                        .padding(.bottom, 6)
                    }
                }
                .padding(.horizontal, 20)
            }
        }
    }
    
    private func periodButton(title: String, period: String) -> some View {
        let isSelected = leaderboardPeriod == period
        return Button {
            withAnimation {
                leaderboardPeriod = period
            }
            Task {
                await loadSocialData()
            }
        } label: {
            Text(title)
                .font(.system(size: 13, weight: .black))
                .foregroundColor(isSelected ? .white : BBColors.text)
                .padding(.vertical, 8)
                .padding(.horizontal, 16)
                .background(isSelected ? BBColors.secondary : BBColors.surfaceAlt)
                .cornerRadius(BBRadius.md)
                .overlay(
                    RoundedRectangle(cornerRadius: BBRadius.md)
                        .stroke(isSelected ? Color(hex: "0284C7") : BBColors.border, lineWidth: 2)
                )
                .background(
                    RoundedRectangle(cornerRadius: BBRadius.md)
                        .fill(isSelected ? Color(hex: "0284C7") : BBColors.borderSubtle)
                        .offset(y: isSelected ? 2 : 0)
                )
                .offset(y: isSelected ? -2 : 0)
        }
    }
    
    // Web-matching Premium Colors for top ranks
    private func rankBadgeColor(_ rank: Int) -> Color {
        switch rank {
        case 1: return Color(hex: "FEF3C7") // Gold light
        case 2: return Color(hex: "F1F5F9") // Silver light
        case 3: return Color(hex: "FFEDD5") // Bronze light
        default: return BBColors.surfaceAlt
        }
    }
    
    private func rankBorderColor(_ rank: Int) -> Color {
        switch rank {
        case 1: return Color(hex: "F59E0B") // Gold border
        case 2: return Color(hex: "94A3B8") // Silver border
        case 3: return Color(hex: "F97316") // Bronze border
        default: return BBColors.border
        }
    }
    
    private func rankShadowColor(_ rank: Int) -> Color {
        switch rank {
        case 1: return Color(hex: "D97706") // Gold deep
        case 2: return Color(hex: "64748B") // Silver deep
        case 3: return Color(hex: "C2410C") // Bronze deep
        default: return BBColors.borderSubtle
        }
    }
    
    private func rankTextColor(_ rank: Int) -> Color {
        switch rank {
        case 1: return Color(hex: "D97706")
        case 2: return Color(hex: "475569")
        case 3: return Color(hex: "C2410C")
        default: return BBColors.textSecondary
        }
    }
    
    // MARK: - Friends View Component
    private var friendsView: some View {
        VStack(spacing: 20) {
            
            // Search Input Block
            VStack(alignment: .leading, spacing: 8) {
                Text("ADD FRIENDS BY HANDLE")
                    .font(.system(size: 10, weight: .heavy))
                    .foregroundColor(BBColors.textSecondary)
                
                HStack(spacing: 12) {
                    HStack(spacing: 8) {
                        Image(systemName: "magnifyingglass")
                            .font(.system(size: 16, weight: .bold))
                            .foregroundColor(BBColors.textSecondary)
                        TextField("Enter handle (e.g. Hung#2117)", text: $searchUsername)
                            .bbInput(isFocused: isSearchFocused)
                            .focused($isSearchFocused)
                            .textInputAutocapitalization(.none)
                            .autocorrectionDisabled()
                    }
                    
                    Button {
                        isSearchFocused = false
                        Task {
                            await searchFriends()
                        }
                    } label: {
                        if isSearching {
                            ProgressView()
                                .tint(.white)
                                .frame(width: 44, height: 44)
                        } else {
                            Text("Search")
                                .font(.system(size: 14, weight: .black))
                                .foregroundColor(.white)
                                .padding(.horizontal, 16)
                                .padding(.vertical, 12)
                        }
                    }
                    .background(BBColors.primary)
                    .cornerRadius(BBRadius.md)
                    .background(
                        RoundedRectangle(cornerRadius: BBRadius.md)
                            .fill(BBColors.primaryHover)
                            .offset(y: 4)
                    )
                    .offset(y: -4)
                    .disabled(isSearching || searchUsername.isEmpty)
                }
            }
            .bbCard()
            .padding(.horizontal, 20)
            
            // Render Search Results Card
            if !searchResults.isEmpty {
                VStack(alignment: .leading, spacing: 14) {
                    Text("SEARCH RESULTS")
                        .font(.system(size: 10, weight: .heavy))
                        .foregroundColor(BBColors.textSecondary)
                        .padding(.horizontal, 4)
                    
                    ForEach(searchResults) { result in
                        HStack(spacing: 12) {
                            ZStack {
                                Circle()
                                    .fill(BBColors.primary.opacity(0.12))
                                    .frame(width: 42, height: 42)
                                Text(String(result.userName.prefix(1).uppercased()))
                                    .font(.system(size: 16, weight: .black))
                                    .foregroundColor(BBColors.primary)
                            }
                            
                            VStack(alignment: .leading, spacing: 2) {
                                Text(result.userName)
                                    .font(.system(size: 14, weight: .heavy))
                                    .foregroundColor(BBColors.text)
                                Text("Lvl \(result.currentLevel)  •  🔥 \(result.loggingStreak) streak")
                                    .font(.system(size: 11, weight: .bold))
                                    .foregroundColor(BBColors.textSecondary)
                            }
                            
                            Spacer()
                            
                            // Add Friend CTA based on relationship
                            relationCTA(result: result)
                        }
                        .padding(12)
                        .background(BBColors.surfaceAlt)
                        .cornerRadius(BBRadius.md)
                        .overlay(
                            RoundedRectangle(cornerRadius: BBRadius.md)
                                .stroke(BBColors.border, lineWidth: 1.5)
                        )
                    }
                }
                .bbCard()
                .padding(.horizontal, 20)
                .transition(.slide.combined(with: .opacity))
            }
            
            // Render Incoming Requests
            if !pendingIn.isEmpty {
                VStack(alignment: .leading, spacing: 12) {
                    HStack(spacing: 6) {
                        Text("📩")
                        Text("PENDING REQUESTS")
                            .font(.system(size: 10, weight: .heavy))
                            .foregroundColor(BBColors.textSecondary)
                    }
                    
                    ForEach(pendingIn) { req in
                        HStack(spacing: 12) {
                            VStack(alignment: .leading, spacing: 3) {
                                Text(req.userName)
                                    .font(.system(size: 14, weight: .heavy))
                                    .foregroundColor(BBColors.text)
                                Text("Lvl \(req.currentLevel)")
                                    .font(.system(size: 11, weight: .bold))
                                    .foregroundColor(BBColors.textSecondary)
                            }
                            
                            Spacer()
                            
                            Button {
                                Task {
                                    await respondToRequest(requestId: req.id, accept: true)
                                }
                            } label: {
                                Image(systemName: "checkmark.circle.fill")
                                    .font(.system(size: 22, weight: .bold))
                                    .foregroundColor(BBColors.primary)
                            }
                            .buttonStyle(.plain)
                            
                            Button {
                                Task {
                                    await respondToRequest(requestId: req.id, accept: false)
                                }
                            } label: {
                                Image(systemName: "xmark.circle.fill")
                                    .font(.system(size: 22, weight: .bold))
                                    .foregroundColor(BBColors.danger)
                            }
                            .buttonStyle(.plain)
                        }
                        .padding(12)
                        .background(BBColors.surfaceAlt)
                        .cornerRadius(BBRadius.md)
                        .overlay(
                            RoundedRectangle(cornerRadius: BBRadius.md)
                                .stroke(BBColors.border, lineWidth: 1.5)
                        )
                    }
                }
                .bbCard()
                .padding(.horizontal, 20)
            }
            
            // 2. Friends Grid Card Layout (Premium Bento-style Side-by-Side)
            VStack(alignment: .leading, spacing: 14) {
                HStack(spacing: 6) {
                    Text("👥")
                    Text("MY FRIENDS (\(friends.count))")
                        .font(.system(size: 10, weight: .heavy))
                        .foregroundColor(BBColors.textSecondary)
                }
                
                if friends.isEmpty {
                    VStack(spacing: 12) {
                        Text("🤝")
                            .font(.system(size: 36))
                        Text("You don't have any friends added yet.")
                            .font(.system(size: 13, weight: .bold))
                            .foregroundColor(BBColors.textSecondary)
                        Text("Search Name#1234 handles to connect!")
                            .font(.system(size: 11, weight: .bold))
                            .foregroundColor(BBColors.textMuted)
                            .multilineTextAlignment(.center)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 24)
                } else {
                    LazyVGrid(columns: columns, spacing: 14) {
                        ForEach(friends) { friend in
                            VStack(alignment: .center, spacing: 10) {
                                // Avatar circle with absolute Level Badge
                                ZStack {
                                    Circle()
                                        .fill(BBColors.primary.opacity(0.12))
                                        .frame(width: 52, height: 52)
                                        .overlay(
                                            Circle()
                                                .stroke(BBColors.primary, lineWidth: 2)
                                        )
                                    Text(String(friend.userName.prefix(1).uppercased()))
                                        .font(.system(size: 20, weight: .black))
                                        .foregroundColor(BBColors.primary)
                                }
                                .overlay(
                                    ZStack {
                                        Circle()
                                            .fill(BBColors.accent)
                                            .frame(width: 20, height: 20)
                                            .overlay(
                                                Circle()
                                                    .stroke(Color.white, lineWidth: 2)
                                            )
                                        Text("\(friend.currentLevel)")
                                            .font(.system(size: 8, weight: .black))
                                            .foregroundColor(.white)
                                    }
                                    .offset(x: 18, y: 18)
                                )
                                .padding(.bottom, 2)
                                
                                VStack(spacing: 3) {
                                    Text(friend.userName.split(separator: "#").first.map(String.init) ?? friend.userName)
                                        .font(.system(size: 14, weight: .black))
                                        .foregroundColor(BBColors.text)
                                        .lineLimit(1)
                                    
                                    if friend.loggingStreak > 0 {
                                        Text("🔥 \(friend.loggingStreak) streak")
                                            .font(.system(size: 10, weight: .bold))
                                            .foregroundColor(.orange)
                                    } else {
                                        Text("💤 inactive")
                                            .font(.system(size: 10, weight: .bold))
                                            .foregroundColor(BBColors.textMuted)
                                    }
                                }
                                
                                // Ghost Unfriend Action
                                Button {
                                    Task {
                                        await unfriendUser(targetId: friend.userId)
                                    }
                                } label: {
                                    HStack(spacing: 4) {
                                        Image(systemName: "person.badge.minus")
                                            .font(.system(size: 10, weight: .bold))
                                        Text("Unfriend")
                                            .font(.system(size: 9, weight: .black))
                                    }
                                    .foregroundColor(BBColors.danger)
                                    .padding(.vertical, 6)
                                    .padding(.horizontal, 10)
                                    .background(BBColors.dangerBg.opacity(0.6))
                                    .cornerRadius(8)
                                }
                                .buttonStyle(.plain)
                            }
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .padding(.horizontal, 10)
                            .background(BBColors.surfaceAlt)
                            .cornerRadius(BBRadius.lg)
                            .overlay(
                                RoundedRectangle(cornerRadius: BBRadius.lg)
                                    .stroke(BBColors.border, lineWidth: 2)
                            )
                        }
                    }
                }
            }
            .bbCard()
            .padding(.horizontal, 20)
        }
    }
    
    // MARK: - Relation Action Helper
    @ViewBuilder
    private func relationCTA(result: SearchUserResult) -> some View {
        switch result.relationship {
        case "friends":
            Text("Friends")
                .font(.system(size: 11, weight: .black))
                .foregroundColor(BBColors.primary)
                .padding(.horizontal, 10)
                .padding(.vertical, 6)
                .background(BBColors.primary.opacity(0.1))
                .cornerRadius(6)
        case "pending_out":
            Text("Pending")
                .font(.system(size: 11, weight: .black))
                .foregroundColor(BBColors.textSecondary)
                .padding(.horizontal, 10)
                .padding(.vertical, 6)
                .background(BBColors.surfaceAlt)
                .cornerRadius(6)
        case "pending_in":
            Text("Accept pending")
                .font(.system(size: 11, weight: .black))
                .foregroundColor(BBColors.secondary)
                .padding(.horizontal, 10)
                .padding(.vertical, 6)
                .background(BBColors.secondary.opacity(0.1))
                .cornerRadius(6)
        default:
            Button {
                Task {
                    await sendRequest(targetId: ClimateDataResultId(result.userId))
                }
            } label: {
                Text("Add")
                    .font(.system(size: 11, weight: .black))
                    .foregroundColor(.white)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(BBColors.primary)
                    .cornerRadius(6)
            }
            .buttonStyle(.plain)
        }
    }
    
    // Convert wrapper
    private func ClimateDataResultId(_ id: Int) -> Int { id }
    
    // MARK: - Network Request Tasks
    
    private func loadSocialData() async {
        do {
            let hub = try await session.loadSocialHub()
            friends = hub.friends
            pendingIn = hub.pendingIn
            pendingOut = hub.pendingOut
            
            let lb = try await session.loadSocialLeaderboard(period: leaderboardPeriod)
            leaders = lb.leaders
        } catch {
            show("Failed loading social stats.", isError: true)
        }
    }
    
    private func searchFriends() async {
        guard !searchUsername.isEmpty else { return }
        isSearching = true
        searchResults = []
        
        do {
            searchResults = try await session.searchUsers(query: searchUsername)
            if searchResults.isEmpty {
                show("No users found matching \"\(searchUsername)\".", isError: true)
            }
        } catch {
            show(error.localizedDescription, isError: true)
        }
        
        isSearching = false
    }
    
    private func sendRequest(targetId: Int) async {
        do {
            try await session.sendFriendRequest(targetId: targetId)
            show("Request sent successfully!", isError: false)
            searchUsername = ""
            searchResults = []
            await loadSocialData()
        } catch {
            show(error.localizedDescription, isError: true)
        }
    }
    
    private func respondToRequest(requestId: Int, accept: Bool) async {
        do {
            try await session.respondFriendRequest(requestId: requestId, accept: accept)
            show(accept ? "Accepted request!" : "Rejected request.", isError: false)
            await loadSocialData()
        } catch {
            show(error.localizedDescription, isError: true)
        }
    }
    
    private func unfriendUser(targetId: Int) async {
        do {
            try await session.unfriend(targetId: targetId)
            show("Removed friend relationship.", isError: false)
            await loadSocialData()
        } catch {
            show(error.localizedDescription, isError: true)
        }
    }
    
    private func show(_ text: String, isError: Bool) {
        message = text
        messageIsError = isError
        
        Task {
            try? await Task.sleep(nanoseconds: 3_000_000_000)
            if message == text {
                withAnimation {
                    message = nil
                }
            }
        }
    }
}
