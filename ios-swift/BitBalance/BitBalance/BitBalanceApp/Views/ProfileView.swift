import SwiftUI

struct ProfileView: View {
    @EnvironmentObject private var session: SessionStore

    @State private var profile: ProfilePayload?
    @State private var selectedTheme = "system"
    @State private var isLoading = false
    @State private var isSavingTheme = false
    @State private var showAuth = false
    @State private var message: String?
    @State private var messageIsError = false

    private let themeOptions = ["system", "light", "dark"]

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()

                ScrollView(showsIndicators: false) {
                    VStack(alignment: .leading, spacing: 22) {
                        profileHero
                        syncCard
//                        PremiumBanner()

                        sectionTitle("Goals")
                        SettingsGroup {
                            SettingsRow(
                                icon: "flame.fill",
                                iconColor: BBColors.danger,
                                title: "Calories",
                                value: calorieGoalText,
                                showsDivider: true,
                                action: openPlanSetup
                            )
                            SettingsRow(
                                icon: "chart.pie.fill",
                                iconColor: BBColors.primary,
                                title: "Macros",
                                value: macroGoalText,
                                showsDivider: true,
                                action: openPlanSetup
                            )
                            SettingsRow(
                                icon: "drop.fill",
                                iconColor: BBColors.secondary,
                                title: "Water",
                                value: waterGoalText,
                                showsDivider: true,
                                action: openPlanSetup
                            )
                            SettingsRow(
                                icon: "scalemass.fill",
                                iconColor: BBColors.accent,
                                title: "Weight",
                                value: weightText,
                                showsDivider: true,
                                action: openPlanSetup
                            )
                            SettingsRow(
                                icon: "arrow.counterclockwise",
                                iconColor: BBColors.textSecondary,
                                title: "Recalculate plan",
                                value: nil,
                                action: openPlanSetup
                            )
                        }

                        sectionTitle("Customize")
                        SettingsGroup {
                            SettingsRow(
                                icon: "paintpalette.fill",
                                iconColor: BBColors.primary,
                                title: "Appearance",
                                value: themeLabel,
                                showsDivider: true,
                                isLoading: isSavingTheme,
                                action: cycleTheme
                            )
//                            SettingsRow(
//                                icon: "globe",
//                                iconColor: BBColors.secondary,
//                                title: "Language",
//                                value: "English",
//                                action: { show("Language settings are coming soon.", isError: false) }
//                            )
                        }

                        sectionTitle("Support")
                        SettingsGroup {
                            SettingsRow(
                                icon: "star.fill",
                                iconColor: BBColors.warning,
                                title: "Rate on App Store",
                                value: nil,
                                showsDivider: true,
                                action: { show("App Store rating will be enabled after release.", isError: false) }
                            )
                            SettingsRow(
                                icon: "lock.shield.fill",
                                iconColor: BBColors.info,
                                title: "Privacy and terms",
                                value: nil,
                                action: { show("Privacy and terms are available on the web app.", isError: false) }
                            )
                        }

                        sectionTitle("Account")
                        SettingsGroup {
                            SettingsRow(
                                icon: "person.crop.circle.fill",
                                iconColor: BBColors.primary,
                                title: accountRowTitle,
                                value: accountRowValue,
                                showsDivider: !isGuestUser,
                                action: accountAction
                            )

                            if !isGuestUser {
                                SettingsRow(
                                    icon: "rectangle.portrait.and.arrow.right",
                                    iconColor: BBColors.danger,
                                    title: "Sign Out",
                                    value: nil,
                                    isDestructive: true,
                                    action: signOut
                                )
                            }
                        }

                        if let message {
                            HStack(spacing: 10) {
                                Image(systemName: messageIsError ? "exclamationmark.triangle.fill" : "checkmark.circle.fill")
                                    .font(BBFont.bodyBold)
                                Text(message)
                                    .font(BBFont.font(BBFont.sm, .bold))
                            }
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .bbAlert(isSuccess: !messageIsError)
                            .transition(.opacity.combined(with: .scale))
                        }
                    }
                    .padding(.horizontal, 20)
                    .padding(.top, 16)
                    .padding(.bottom, 44)
                }
                .refreshable {
                    await load()
                }
            }
            .navigationTitle("Settings")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        Task { await load() }
                    } label: {
                        if isLoading {
                            ProgressView()
                                .tint(BBColors.primary)
                        } else {
                            Image(systemName: "arrow.clockwise")
                                .font(BBFont.font(15, .black))
                                .foregroundColor(BBColors.primary)
                        }
                    }
                    .disabled(isLoading)
                }
            }
            .task {
                if profile == nil {
                    await load()
                }
            }
            .sheet(isPresented: $showAuth) {
                AuthEntryView()
                    .onDisappear {
                        if session.user != nil {
                            session.exitGuestMode()
                            Task { await load() }
                        }
                    }
            }
        }
    }

    private var profileHero: some View {
        Button(action: accountAction) {
            HStack(spacing: 16) {
                ZStack(alignment: .bottomTrailing) {
                    Circle()
                        .fill(
                            LinearGradient(
                                colors: [BBColors.primary, BBColors.secondary],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        )
                        .frame(width: 82, height: 82)
                        .overlay(
                            Circle()
                                .stroke(BBColors.primary.opacity(0.75), lineWidth: 4)
                                .allowsHitTesting(false)
                        )

//                    Text(avatarInitials)
//                        .font(.system(size: 28, weight: .black))
//                        .foregroundColor(.white)

                    Text("FREE")
                        .font(.system(size: 10, weight: .black))
                        .foregroundColor(BBColors.textSecondary)
                        .padding(.horizontal, 10)
                        .padding(.vertical, 4)
                        .background(BBColors.background)
                        .clipShape(Capsule())
                        .overlay(
                            Capsule()
                                .stroke(BBColors.border, lineWidth: 1)
                                .allowsHitTesting(false)
                        )
                        .offset(x: 10, y: 8)
                }

                VStack(alignment: .leading, spacing: 6) {
                    Text(displayName)
                        .font(BBFont.font(28, .black))
                        .foregroundColor(BBColors.text)
                        .lineLimit(1)
                        .minimumScaleFactor(0.75)

                    HStack(spacing: 8) {
                        Image(systemName: goalStatusIcon)
                            .font(BBFont.font(13, .black))
                            .foregroundColor(BBColors.textSecondary)

                        Text(goalStatusText)
                            .font(BBFont.font(17, .bold))
                            .foregroundColor(BBColors.textSecondary)
                            .lineLimit(1)
                            .minimumScaleFactor(0.75)
                    }
                }

                Spacer(minLength: 8)

                Image(systemName: "chevron.right")
                    .font(BBFont.font(22, .heavy))
                    .foregroundColor(BBColors.textMuted)
            }
            .padding(18)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .buttonStyle(.plain)
        .bbCard(radius: BBRadius.xl, padding: 0)
    }

    private var syncCard: some View {
        Button {
            if isGuestUser {
                showAuth = true
            } else {
                show("Your BitBalance data is synced.", isError: false)
            }
        } label: {
            HStack(spacing: 16) {
                SettingsIconBox(
                    icon: isGuestUser ? "arrow.right.circle.fill" : "checkmark.circle.fill",
                    color: BBColors.primary,
                    size: 56
                )

                VStack(alignment: .leading, spacing: 3) {
                    Text(isGuestUser ? "Sign In" : "Signed in")
                        .font(BBFont.titleBold)
                        .foregroundColor(BBColors.primary)

                    Text(isGuestUser ? "Sign in to sync your data across devices" : currentEmail)
                        .font(BBFont.bodyBold)
                        .foregroundColor(BBColors.textSecondary)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }

                Spacer()

                Image(systemName: "chevron.right")
                    .font(BBFont.font(20, .heavy))
                    .foregroundColor(BBColors.textMuted)
            }
            .padding(18)
        }
        .buttonStyle(.plain)
        .bbCard(radius: BBRadius.xl, padding: 0)
    }

    private func sectionTitle(_ title: String) -> some View {
        Text(title)
            .font(BBFont.font(22, .black))
            .foregroundColor(BBColors.textSecondary)
            .padding(.horizontal, 8)
            .padding(.top, 4)
    }

    private var currentUser: UserSession? {
        profile?.user ?? session.user
    }

    private var isGuestUser: Bool {
        session.isGuest || currentUser == nil
    }

    private var displayName: String {
        guard let user = currentUser else { return "Guest" }
        let fullName = [user.firstName, user.lastName ?? ""]
            .map { $0.trimmingCharacters(in: .whitespacesAndNewlines) }
            .filter { !$0.isEmpty }
            .joined(separator: " ")
        return fullName.isEmpty ? user.handle : fullName
    }

    private var currentEmail: String {
        currentUser?.email ?? "Not signed in"
    }

    private var accountRowTitle: String {
        isGuestUser ? "Create account" : "Account"
    }

    private var accountRowValue: String? {
        isGuestUser ? "Required to sync" : currentUser?.handle
    }

    private var goalStatusIcon: String {
        bmiValue == nil ? "minus.circle.fill" : "equal.circle.fill"
    }

    private var goalStatusText: String {
        if let bmiValue {
            return "Maintain - BMI \(formatOneDecimal(bmiValue))"
        }
        return isGuestUser ? "Guest mode" : "Plan not set"
    }

    private var bmiValue: Double? {
        guard let weight = profile?.physical.weight,
              let height = profile?.physical.height,
              height > 0 else {
            return nil
        }
        let meters = height / 100
        return weight / (meters * meters)
    }

    private var calorieGoalText: String {
        guard let calories = profile?.goal?.calorieGoal else { return "Set up" }
        return "\(calories) kcal"
    }

    private var macroGoalText: String {
        guard let calories = profile?.goal?.calorieGoal else { return "Set up" }
        let protein = Int(round(Double(calories) * 0.30 / 4))
        let carbs = Int(round(Double(calories) * 0.45 / 4))
        let fat = Int(round(Double(calories) * 0.25 / 9))
        return "\(protein)/\(carbs)/\(fat) g"
    }

    private var waterGoalText: String {
        guard let weight = profile?.physical.weight else { return "2,000 ml" }
        return "\(Int(round(weight * 35))) ml"
    }

    private var weightText: String {
        guard let weight = profile?.physical.weight else { return "Set up" }
        return "\(formatOneDecimal(weight)) kg"
    }

    private var themeLabel: String {
        switch selectedTheme {
        case "light":
            return "Light"
        case "dark":
            return "Dark"
        default:
            return "System"
        }
    }

    private func accountAction() {
        if isGuestUser {
            showAuth = true
        } else {
            show("Account details are managed from the web profile for now.", isError: false)
        }
    }

    private func openPlanSetup() {
        guard !isGuestUser else {
            showAuth = true
            return
        }
        session.needsOnboarding = true
    }

    private func cycleTheme() {
        guard !isSavingTheme else { return }
        let currentIndex = themeOptions.firstIndex(of: selectedTheme) ?? 0
        let nextTheme = themeOptions[(currentIndex + 1) % themeOptions.count]

        guard let profile else {
            selectedTheme = nextTheme
            return
        }

        Task {
            await saveTheme(nextTheme, using: profile)
        }
    }

    private func signOut() {
        Task {
            await session.signOut()
            profile = nil
        }
    }

    private func load() async {
        guard !isGuestUser else {
            profile = nil
            selectedTheme = currentUser?.themePreference ?? "system"
            return
        }

        isLoading = true
        message = nil

        do {
            let payload = try await session.loadProfile()
            profile = payload
            selectedTheme = payload.user.themePreference ?? "system"
        } catch {
            show(error.localizedDescription, isError: true)
        }

        isLoading = false
    }

    private func saveTheme(_ theme: String, using profile: ProfilePayload) async {
        isSavingTheme = true
        message = nil

        let payload = ProfileUpdatePayload(
            firstName: profile.user.firstName,
            lastName: profile.user.lastName ?? "",
            userName: profile.user.handle,
            email: profile.user.email,
            bio: profile.bio ?? "",
            themePreference: theme,
            calorieGoal: profile.goal.map { String($0.calorieGoal) } ?? "",
            age: profile.physical.age.map { String($0) } ?? "",
            gender: profile.physical.gender ?? "",
            weight: profile.physical.weight.map { formatInputNumber($0) } ?? "",
            height: profile.physical.height.map { formatInputNumber($0) } ?? ""
        )

        do {
            let updated = try await session.updateProfile(payload)
            self.profile = updated
            selectedTheme = updated.user.themePreference ?? theme
            show("Appearance updated.", isError: false)
        } catch {
            show(error.localizedDescription, isError: true)
        }

        isSavingTheme = false
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

    private func formatOneDecimal(_ value: Double) -> String {
        if value.rounded() == value {
            return String(format: "%.0f", value)
        }
        return String(format: "%.1f", value)
    }

    private func formatInputNumber(_ value: Double) -> String {
        if value.rounded() == value {
            return String(Int(value))
        }
        return String(format: "%.1f", value)
    }
}

private struct PremiumBanner: View {
    var body: some View {
        HStack(spacing: 16) {
            ZStack {
                Circle()
                    .fill(BBColors.accent.opacity(0.25))
                    .frame(width: 76, height: 76)
                Circle()
                    .fill(BBColors.warning)
                    .frame(width: 58, height: 58)
                    .overlay(
                        Circle()
                            .stroke(BBColors.accentHover, lineWidth: 4)
                            .allowsHitTesting(false)
                    )
                Image(systemName: "leaf.fill")
                    .font(BBFont.font(24, .black))
                    .foregroundColor(BBColors.primaryHover)
                    .offset(x: 16, y: -22)
            }

            VStack(alignment: .leading, spacing: 6) {
                Text("Go Premium")
                    .font(BBFont.font(28, .black))
                    .foregroundColor(.white)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)

                Text("$4.17 / month")
                    .font(BBFont.font(18, .bold))
                    .foregroundColor(.white.opacity(0.85))
            }

            Spacer(minLength: 8)

            VStack(spacing: 2) {
                Text("SAVE")
                    .font(BBFont.font(13, .black))
                    .tracking(2)
                Text("47%")
                    .font(BBFont.font(28, .black))
            }
            .foregroundColor(.white)
            .padding(.horizontal, 18)
            .padding(.vertical, 13)
            .overlay(
                RoundedRectangle(cornerRadius: BBRadius.lg)
                    .stroke(.white.opacity(0.45), lineWidth: 2)
                    .allowsHitTesting(false)
            )
        }
        .padding(18)
        .background(
            LinearGradient(
                colors: [BBColors.secondary, BBColors.primary.opacity(0.75)],
                startPoint: .leading,
                endPoint: .trailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: BBRadius.xl))
        .shadow(color: BBColors.secondary.opacity(0.18), radius: 12, x: 0, y: 8)
    }
}

private struct SettingsGroup<Content: View>: View {
    let content: Content

    init(@ViewBuilder content: () -> Content) {
        self.content = content()
    }

    var body: some View {
        VStack(spacing: 0) {
            content
        }
        .background(BBColors.surface)
        .clipShape(RoundedRectangle(cornerRadius: BBRadius.xl))
        .overlay(
            RoundedRectangle(cornerRadius: BBRadius.xl)
                .stroke(BBColors.border, lineWidth: 2)
                .allowsHitTesting(false)
        )
        .background(
            RoundedRectangle(cornerRadius: BBRadius.xl)
                .fill(BBColors.borderSubtle)
                .offset(y: 8)
        )
        .shadow(color: .black.opacity(0.06), radius: 8, x: 0, y: 2)
        .padding(.bottom, 8)
    }
}

private struct SettingsRow: View {
    let icon: String
    let iconColor: Color
    let title: String
    let value: String?
    var showsDivider = false
    var isDestructive = false
    var isLoading = false
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(spacing: 0) {
                HStack(spacing: 16) {
                    SettingsIconBox(icon: icon, color: iconColor)

                    Text(title)
                        .font(BBFont.font(22, .heavy))
                        .foregroundColor(isDestructive ? BBColors.danger : BBColors.text)
                        .lineLimit(1)
                        .minimumScaleFactor(0.75)

                    Spacer(minLength: 10)

                    if isLoading {
                        ProgressView()
                            .tint(BBColors.primary)
                    } else if let value {
                        Text(value)
                            .font(BBFont.heading)
                            .foregroundColor(BBColors.textSecondary)
                            .lineLimit(1)
                            .minimumScaleFactor(0.7)
                    }

                    Image(systemName: "chevron.right")
                        .font(BBFont.font(18, .black))
                        .foregroundColor(BBColors.textMuted)
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 18)

                if showsDivider {
                    Rectangle()
                        .fill(BBColors.border.opacity(0.8))
                        .frame(height: 1)
                        .padding(.leading, 78)
                }
            }
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
    }
}

private struct SettingsIconBox: View {
    let icon: String
    let color: Color
    var size: CGFloat = 42

    var body: some View {
        Image(systemName: icon)
            .font(BBFont.font(size * 0.44, .black))
            .foregroundColor(color)
            .frame(width: size, height: size)
            .background(color.opacity(0.16))
            .clipShape(RoundedRectangle(cornerRadius: BBRadius.md))
    }
}

#Preview("Default") {
    ProfileView()
        .environmentObject(SessionStore.preview)
}

#Preview("Dark Mode") {
    ProfileView()
        .environmentObject(SessionStore.preview)
        .preferredColorScheme(.dark)
}
