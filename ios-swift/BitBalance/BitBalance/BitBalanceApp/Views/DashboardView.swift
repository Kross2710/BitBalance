import SwiftUI

struct DashboardView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var dashboard: DashboardDayPayload?
    @State private var selectedDate = Date()
    @State private var selectedStatsTab: DashboardStatsTab = .nutrition
    @State private var errorMessage: String?
    @State private var isLoading = false

    private var greetingText: String {
        let hour = Calendar.current.component(.hour, from: Date())
        let name = session.user?.firstName ?? "Champion"
        if hour < 12 {
            return "Good morning, \(name)"
        } else if hour < 17 {
            return "Good afternoon, \(name)"
        } else {
            return "Good evening, \(name)"
        }
    }

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()

                ScrollView {
                    VStack(alignment: .leading, spacing: 20) {
                        DashboardCalendarStrip(selectedDate: $selectedDate)

                        if let errorMessage {
                            HStack(spacing: 8) {
                                Image(systemName: "exclamationmark.triangle.fill")
                                Text(errorMessage)
                                    .lineLimit(3)
                            }
                            .font(BBFont.font(BBFont.sm, .bold))
                            .bbAlert(isSuccess: false)
                        }

                        if let dashboard {
                            WelcomeBanner(greeting: greetingText, payload: dashboard)
                            TodayProgressCard(payload: dashboard)
                            StatsHubCard(payload: dashboard, selectedTab: $selectedStatsTab)
                            LevelCard(payload: dashboard)
                            MascotRoomCard(payload: dashboard)
                            StreakCard(payload: dashboard)
                            FocusCard(payload: dashboard)
                            OverviewSideSummary(payload: dashboard)
                        } else {
                            DashboardLoadingCard()
                        }
                    }
                    .padding(20)
                    .padding(.bottom, 12)
                }
                .refreshable {
                    await loadDashboardDay()
                }
            }

            .task {
                await loadDashboardDay()
            }
            .onChange(of: selectedDate) { _, _ in
                Task { await loadDashboardDay() }
            }
        }
    }

    private func loadDashboardDay() async {
        isLoading = true
        defer { isLoading = false }

        let dateString = DashboardDateFormat.api.string(from: selectedDate)

        if session.isGuest {
            dashboard = DashboardDayPayload.mock(for: selectedDate)
            errorMessage = nil
            return
        }

        do {
            dashboard = try await session.loadDashboardDay(date: dateString)
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
            if dashboard == nil {
                dashboard = DashboardDayPayload.empty(for: selectedDate)
            }
        }
    }
}

private enum DashboardStatsTab: String, CaseIterable, Identifiable {
    case nutrition = "Nutrition"
    case weight = "Weight"
    case meals = "Meals"

    var id: String { rawValue }

    var icon: String {
        switch self {
        case .nutrition: return "chart.bar.fill"
        case .weight: return "scalemass.fill"
        case .meals: return "circle.grid.2x2.fill"
        }
    }
}

private enum DashboardDateFormat {
    static let api: DateFormatter = {
        let formatter = DateFormatter()
        formatter.calendar = Calendar(identifier: .gregorian)
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter
    }()

    static let monthTitle: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "MMMM yyyy"
        return formatter
    }()

    static let dayName: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "E"
        return formatter
    }()
}

// MARK: - Top Layout

private struct DashboardCalendarStrip: View {
    @Binding var selectedDate: Date

    private var calendar: Calendar { Calendar.current }

    private var monthDays: [Date] {
        guard
            let interval = calendar.dateInterval(of: .month, for: selectedDate),
            let dayRange = calendar.range(of: .day, in: .month, for: selectedDate)
        else { return [] }

        return dayRange.compactMap { day -> Date? in
            calendar.date(byAdding: .day, value: day - 1, to: interval.start)
        }
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 12) {
                monthButton(systemName: "chevron.left") {
                    shiftMonth(-1)
                }

                Text(DashboardDateFormat.monthTitle.string(from: selectedDate))
                    .font(BBFont.font(18, .black))
                    .foregroundColor(BBColors.text)
                    .frame(maxWidth: .infinity)

                monthButton(systemName: "chevron.right") {
                    shiftMonth(1)
                }
                .opacity(canMoveToNextMonth ? 1 : 0.45)
                .disabled(!canMoveToNextMonth)
            }

            ScrollViewReader { proxy in
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 10) {
                        ForEach(monthDays, id: \.self) { date in
                            let isFuture = calendar.startOfDay(for: date) > calendar.startOfDay(for: Date())
                            DayChip(
                                date: date,
                                isSelected: calendar.isDate(date, inSameDayAs: selectedDate),
                                isToday: calendar.isDateInToday(date),
                                isDisabled: isFuture
                            ) {
                                selectedDate = date
                            }
                            .id(DashboardDateFormat.api.string(from: date))
                        }
                    }
                    .padding(.vertical, 2)
                    .padding(.horizontal, 2)
                }
                .onAppear {
                    proxy.scrollTo(DashboardDateFormat.api.string(from: selectedDate), anchor: .center)
                }
                .onChange(of: selectedDate) { _, date in
                    withAnimation(.easeInOut(duration: 0.2)) {
                        proxy.scrollTo(DashboardDateFormat.api.string(from: date), anchor: .center)
                    }
                }
            }
        }
        .padding(.vertical, 10)
    }

    private var canMoveToNextMonth: Bool {
        guard let nextMonth = calendar.date(byAdding: .month, value: 1, to: selectedDate) else {
            return false
        }
        let todayComponents = calendar.dateComponents([.year, .month], from: Date())
        let nextComponents = calendar.dateComponents([.year, .month], from: nextMonth)
        guard
            let todayYear = todayComponents.year,
            let todayMonth = todayComponents.month,
            let nextYear = nextComponents.year,
            let nextMonthNumber = nextComponents.month
        else { return false }

        return nextYear < todayYear || (nextYear == todayYear && nextMonthNumber <= todayMonth)
    }

    private func monthButton(systemName: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: systemName)
                .font(BBFont.font(14, .bold))
                .foregroundColor(BBColors.textSecondary)
                .frame(width: 32, height: 32)
                .background(BBColors.surfaceAlt)
                .clipShape(Circle())
                .overlay(Circle().stroke(BBColors.border, lineWidth: 1.5))
        }
    }

    private func shiftMonth(_ amount: Int) {
        guard let newMonth = calendar.date(byAdding: .month, value: amount, to: selectedDate) else {
            return
        }

        if amount > 0 && !canMoveToNextMonth {
            return
        }

        let day = min(calendar.component(.day, from: selectedDate), calendar.range(of: .day, in: .month, for: newMonth)?.count ?? 1)
        var components = calendar.dateComponents([.year, .month], from: newMonth)
        components.day = day
        if let adjusted = calendar.date(from: components) {
            selectedDate = min(adjusted, Date())
        }
    }
}

private struct DayChip: View {
    let date: Date
    let isSelected: Bool
    let isToday: Bool
    let isDisabled: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(spacing: 4) {
                Text(String(DashboardDateFormat.dayName.string(from: date).prefix(1)))
                    .font(.system(size: 11, weight: .black))
                    .foregroundColor(isSelected ? .white : BBColors.textSecondary)
                
                Text("\(Calendar.current.component(.day, from: date))")
                    .font(BBFont.font(16, .black))
                    .foregroundColor(isSelected ? .white : (isDisabled ? BBColors.textMuted : BBColors.text))
                
                if isToday {
                    Circle()
                        .fill(isSelected ? .white : BBColors.primary)
                        .frame(width: 4, height: 4)
                } else {
                    Spacer().frame(height: 4)
                }
            }
            .frame(width: 46, height: 58)
            .background(
                Group {
                    if isSelected {
                        BBColors.primary
                    } else {
                        BBColors.surfaceAlt
                    }
                }
            )
            .cornerRadius(10)
            .overlay(
                Group {
                    if isSelected {
                        RoundedRectangle(cornerRadius: 10)
                            .stroke(BBColors.primaryHover, lineWidth: 1)
                    } else if isDisabled {
                        RoundedRectangle(cornerRadius: 10)
                            .stroke(BBColors.border, style: StrokeStyle(lineWidth: 1.5, lineCap: .round, dash: [4, 4]))
                    } else if isToday {
                        RoundedRectangle(cornerRadius: 10)
                            .stroke(BBColors.primary, lineWidth: 1.5)
                    } else {
                        RoundedRectangle(cornerRadius: 10)
                            .stroke(BBColors.border, lineWidth: 1.5)
                    }
                }
            )
            .opacity(isDisabled ? 0.6 : 1.0)
        }
        .disabled(isDisabled)
    }
}

private struct WelcomeBanner: View {
    let greeting: String
    let payload: DashboardDayPayload

    private var message: String {
        guard let goal = payload.calorieGoal, goal > 0 else {
            return "Set a daily goal to unlock progress tracking."
        }
        if payload.totalCalories == 0 {
            return "No intake logged yet. Start with your first meal."
        }
        if payload.progressPercentage >= 100 {
            return "Daily goal reached. Keep the rest of today steady."
        }
        return "\(Int(payload.progressPercentage))% of your daily goal is complete."
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(alignment: .top, spacing: 14) {
                VStack(alignment: .leading, spacing: 6) {
                    Text(greeting)
                        .font(BBFont.titleBold)
                    Text(message)
                        .font(BBFont.font(15, .bold))
                        .foregroundColor(.white.opacity(0.92))
                        .fixedSize(horizontal: false, vertical: true)
                }
                Spacer()
                Image(systemName: "flame.fill")
                    .font(.system(size: 40, weight: .black))
                    .foregroundColor(.white.opacity(0.95))
            }

            HStack(spacing: 10) {
                WelcomeChip(icon: "target", text: "Goal \(Int(payload.progressPercentage))%")
                WelcomeChip(icon: "trophy.fill", text: "Level \(payload.currentLevel)")
            }
        }
        .padding(22)
        .background(BBColors.primaryGradient)
        .cornerRadius(BBRadius.lg)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.lg).stroke(BBColors.primaryHover, lineWidth: 2))
        .background(RoundedRectangle(cornerRadius: BBRadius.lg).fill(BBColors.primaryHover.opacity(0.85)).offset(y: 8))
        .shadow(color: .black.opacity(0.08), radius: 8, x: 0, y: 2)
        .padding(.bottom, 8)
    }
}

private struct WelcomeChip: View {
    let icon: String
    let text: String

    var body: some View {
        HStack(spacing: 7) {
            Image(systemName: icon)
                .font(BBFont.font(12, .black))
            Text(text.uppercased())
                .font(BBFont.font(12, .black))
                .lineLimit(1)
                .minimumScaleFactor(0.75)
        }
        .foregroundColor(.white)
        .frame(maxWidth: .infinity)
        .padding(.horizontal, 10)
        .padding(.vertical, 10)
        .background(.white.opacity(0.16))
        .cornerRadius(BBRadius.md)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(.white.opacity(0.14), lineWidth: 1))
    }
}

private struct TodayProgressCard: View {
    let payload: DashboardDayPayload

    private var statusColor: Color {
        payload.statusClass == "overlimit" ? BBColors.danger : BBColors.primary
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(alignment: .firstTextBaseline) {
                Text("Today")
                    .font(BBFont.font(BBFont.lg, .black))
                    .foregroundColor(BBColors.text)
                Spacer()
                Text(payload.statusClass == "overlimit" ? "Overlimit" : "Ongoing")
                    .font(BBFont.captionBold)
                    .foregroundColor(statusColor)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(statusColor.opacity(0.12))
                    .cornerRadius(BBRadius.pill)
            }

            HStack(alignment: .firstTextBaseline, spacing: 4) {
                Text("\(payload.totalCalories)")
                    .font(.system(size: 42, weight: .black))
                    .foregroundColor(statusColor)
                Text("kcal")
                    .font(BBFont.bodyBold)
                    .foregroundColor(BBColors.textSecondary)
            }

            ProgressTrack(value: payload.progressPercentage, color: statusColor, height: 14)

            HStack {
                Text("Goal")
                    .font(BBFont.font(13, .bold))
                    .foregroundColor(BBColors.textSecondary)
                Spacer()
                Text(payload.calorieGoal.map { "\($0) kcal" } ?? "Not set")
                    .font(BBFont.font(13, .black))
                    .foregroundColor(BBColors.text)
            }
        }
        .bbCard()
    }
}

// MARK: - Stats Hub

private struct StatsHubCard: View {
    let payload: DashboardDayPayload
    @Binding var selectedTab: DashboardStatsTab

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            HStack(spacing: 4) {
                ForEach(DashboardStatsTab.allCases) { tab in
                    Button {
                        withAnimation(.interactiveSpring(response: 0.22, dampingFraction: 0.86)) {
                            selectedTab = tab
                        }
                    } label: {
                        HStack(spacing: 5) {
                            Image(systemName: tab.icon)
                            Text(tab.rawValue)
                        }
                        .font(.system(size: 11, weight: .black))
                        .foregroundColor(selectedTab == tab ? BBColors.secondary : BBColors.textSecondary)
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 10)
                        .background(selectedTab == tab ? BBColors.surface : Color.clear)
                        .cornerRadius(10)
                        .overlay(
                            RoundedRectangle(cornerRadius: 10)
                                .stroke(selectedTab == tab ? BBColors.border : Color.clear, lineWidth: 2)
                        )
                        .background(
                            RoundedRectangle(cornerRadius: 10)
                                .fill(selectedTab == tab ? BBColors.borderSubtle : Color.clear)
                                .offset(y: selectedTab == tab ? 4 : 0)
                        )
                    }
                }
            }
            .padding(4)
            .background(BBColors.surfaceAlt)
            .cornerRadius(BBRadius.md)
            .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))

            switch selectedTab {
            case .nutrition:
                NutritionStatsView(payload: payload)
            case .weight:
                WeightStatsView(payload: payload)
            case .meals:
                MealsBentoView(payload: payload)
            }
        }
        .bbCard()
    }
}

private struct NutritionStatsView: View {
    let payload: DashboardDayPayload

    var body: some View {
        VStack(alignment: .leading, spacing: 18) {
            SectionHeader(
                icon: "chart.line.uptrend.xyaxis",
                title: "Last 7 days",
                trailing: payload.averageCalories.map { "Avg \($0) kcal" } ?? "Avg N/A"
            )
            CalorieLineChart(labels: payload.history.labels, values: payload.history.calories)
                .frame(height: 150)

            VStack(alignment: .leading, spacing: 12) {
                SectionHeader(icon: "square.stack.3d.up.fill", title: "Macro trend", trailing: nil)
                MacroTrendChart(history: payload.history)
                    .frame(height: 160)
                MacroLegend()
            }
            .padding(14)
            .background(BBColors.surfaceAlt)
            .cornerRadius(BBRadius.md)
            .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))
        }
    }
}

private struct WeightStatsView: View {
    let payload: DashboardDayPayload

    private var latestWeight: Double? {
        payload.weightHistory.last?.weight ?? payload.physical.weight
    }

    private var weightDiff: Double {
        guard payload.weightHistory.count >= 2 else { return 0 }
        let previous = payload.weightHistory[payload.weightHistory.count - 2].weight
        let current = payload.weightHistory[payload.weightHistory.count - 1].weight
        return current - previous
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(alignment: .center) {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Weight")
                        .font(.system(size: 18, weight: .black))
                        .foregroundColor(BBColors.text)
                    HStack(alignment: .firstTextBaseline, spacing: 4) {
                        Text(latestWeight.map { String(format: "%.1f", $0) } ?? "--")
                            .font(.system(size: 34, weight: .black))
                            .foregroundColor(BBColors.text)
                        Text("kg")
                            .font(BBFont.font(13, .bold))
                            .foregroundColor(BBColors.textSecondary)
                    }
                }
                Spacer()
                TrendBadge(diff: weightDiff)
            }

            WeightLineChart(points: payload.weightHistory)
                .frame(height: 150)

            HStack(spacing: 10) {
                MiniActionButton(icon: "list.bullet", title: "History", color: BBColors.secondary)
                MiniActionButton(icon: "plus", title: "Log", color: BBColors.primary)
            }
        }
    }
}

private struct MealsBentoView: View {
    let payload: DashboardDayPayload

    private var meals: [MealSummary] {
        [
            MealSummary(key: "breakfast", title: "Breakfast", emoji: "☀️", color: Color(hex: "FF3366")),
            MealSummary(key: "lunch", title: "Lunch", emoji: "🍔", color: BBColors.secondary),
            MealSummary(key: "dinner", title: "Dinner", emoji: "🌙", color: BBColors.accent),
            MealSummary(key: "snack", title: "Snack", emoji: "🍎", color: BBColors.primary)
        ]
    }

    var body: some View {
        VStack(spacing: 16) {
            MealRingsView(totalCalories: payload.totalCalories, meals: meals, totals: payload.mealCategories)
                .frame(height: 245)

            LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
                ForEach(meals) { meal in
                    MealSlotCard(
                        meal: meal,
                        calories: payload.mealCategories[meal.key] ?? 0,
                        entries: payload.entries.filter { $0.mealCategory.lowercased() == meal.key }
                    )
                }
            }
        }
    }
}

private struct MealSummary: Identifiable {
    let key: String
    let title: String
    let emoji: String
    let color: Color
    var id: String { key }
}

// MARK: - Habit, Focus, Side Summary

private struct LevelCard: View {
    let payload: DashboardDayPayload

    var body: some View {
        HStack(spacing: 18) {
            ZStack {
                Image(systemName: "star.fill")
                    .font(.system(size: 58))
                    .foregroundColor(BBColors.accent)
                    .shadow(color: BBColors.accent.opacity(0.28), radius: 6, x: 0, y: 3)
                Text("\(payload.currentLevel)")
                    .font(BBFont.font(BBFont.lg, .black))
                    .foregroundColor(.white)
                    .offset(y: -2)
            }

            VStack(alignment: .leading, spacing: 7) {
                Text("LEVEL")
                    .font(BBFont.font(BBFont.xs, .heavy))
                    .foregroundColor(BBColors.textSecondary)
                Text("\(payload.totalXp) XP")
                    .font(.system(size: 26, weight: .black))
                    .foregroundColor(BBColors.text)
                ProgressTrack(value: Double(payload.xpProgressPercentage), color: BBColors.accent, height: 9)
                Text("\(payload.xpProgressPercentage)% to next level")
                    .font(.system(size: 11, weight: .bold))
                    .foregroundColor(BBColors.textSecondary)
            }
            Spacer()
        }
        .bbCard()
    }
}

private struct MascotRoomCard: View {
    let payload: DashboardDayPayload

    private var stateText: String {
        if payload.statusClass == "overlimit" {
            return "Take it easy for the rest of today."
        }
        if payload.focus.macroFocus?.key == "protein" {
            return "Protein is your best next move."
        }
        if payload.streak.current > 0 {
            return "Your streak is active today."
        }
        return "Log a meal to start today's rhythm."
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            SectionHeader(icon: "sparkles", title: "Coach mascot", trailing: nil)

            HStack(spacing: 16) {
                ZStack {
                    Circle()
                        .fill(BBColors.primary.opacity(payload.statusClass == "overlimit" ? 0.08 : 0.18))
                        .frame(width: 116, height: 116)
                    VStack(spacing: 6) {
                        HStack(spacing: 10) {
                            Circle().fill(BBColors.text).frame(width: 13, height: 13)
                            Circle().fill(BBColors.text).frame(width: 13, height: 13)
                        }
                        RoundedRectangle(cornerRadius: 20)
                            .fill(payload.statusClass == "overlimit" ? BBColors.warning : BBColors.primary)
                            .frame(width: 70, height: 54)
                            .overlay(
                                Image(systemName: payload.statusClass == "overlimit" ? "zzz" : "heart.fill")
                                    .font(BBFont.font(BBFont.lg, .black))
                                    .foregroundColor(.white)
                            )
                    }
                }

                VStack(alignment: .leading, spacing: 8) {
                    Text(stateText)
                        .font(BBFont.bodyBold)
                        .foregroundColor(BBColors.text)
                        .fixedSize(horizontal: false, vertical: true)
                    Text("Tap into the same daily feedback loop as the web overview.")
                        .font(BBFont.font(BBFont.xs, .bold))
                        .foregroundColor(BBColors.textSecondary)
                        .fixedSize(horizontal: false, vertical: true)
                }
            }
        }
        .bbCard()
    }
}

private struct StreakCard: View {
    let payload: DashboardDayPayload

    private var nextMilestone: Int {
        [7, 14, 30, 60, 100, 180, 365].first { $0 > payload.streak.current } ?? 365
    }

    private var previousMilestone: Int {
        [7, 14, 30, 60, 100, 180, 365].last { $0 <= payload.streak.current } ?? 0
    }

    private var progress: Double {
        if payload.streak.current >= 365 { return 100 }
        let span = max(nextMilestone - previousMilestone, 1)
        return Double(payload.streak.current - previousMilestone) / Double(span) * 100
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 14) {
                ZStack {
                    Circle()
                        .fill(BBColors.accent.opacity(0.16))
                        .frame(width: 58, height: 58)
                    Image(systemName: "flame.fill")
                        .font(.system(size: 26, weight: .black))
                        .foregroundColor(BBColors.accent)
                }

                VStack(alignment: .leading, spacing: 2) {
                    Text("Logging streak")
                        .font(BBFont.font(17, .black))
                        .foregroundColor(BBColors.text)
                    HStack(alignment: .firstTextBaseline, spacing: 4) {
                        Text("\(payload.streak.current)")
                            .font(.system(size: 32, weight: .black))
                            .foregroundColor(BBColors.text)
                        Text("days")
                            .font(BBFont.font(13, .bold))
                            .foregroundColor(BBColors.textSecondary)
                    }
                }
            }

            Text(payload.streak.current > 0 ? "Keep the chain alive with one log today." : "Start a streak by logging your first meal.")
                .font(BBFont.font(13, .bold))
                .foregroundColor(BBColors.textSecondary)

            ProgressTrack(value: progress, color: BBColors.accent, height: 10)

            HStack {
                Label("\(max(nextMilestone - payload.streak.current, 0)) days to \(nextMilestone)", systemImage: "flag.checkered")
                Spacer()
                Label("\(payload.streak.freezes)", systemImage: "snowflake")
            }
            .font(BBFont.font(12, .black))
            .foregroundColor(BBColors.textSecondary)
            .lineLimit(1)
            .minimumScaleFactor(0.75)
        }
        .bbCard()
    }
}

private struct FocusCard: View {
    let payload: DashboardDayPayload

    private var title: String {
        if let remaining = payload.focus.calorieRemaining {
            return "\(remaining) kcal left today"
        }
        if let overBy = payload.focus.calorieOverBy {
            return "\(overBy) kcal over target"
        }
        return "Set a calorie goal"
    }

    private var statusColor: Color {
        payload.focus.tone == "alert" ? BBColors.danger : BBColors.primary
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                Label("Today's focus", systemImage: "location.north.line.fill")
                    .font(BBFont.font(12, .black))
                    .foregroundColor(BBColors.textSecondary)
                Spacer()
                Text(payload.focus.status.uppercased())
                    .font(.system(size: 11, weight: .black))
                    .foregroundColor(statusColor)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(statusColor.opacity(0.12))
                    .cornerRadius(BBRadius.pill)
            }

            VStack(alignment: .leading, spacing: 6) {
                Text(title)
                    .font(BBFont.font(22, .black))
                    .foregroundColor(BBColors.text)
                Text(payload.focus.tone == "alert" ? "Adjust the next meal and keep the weekly trend steady." : "Use the next meal to balance calories and macros.")
                    .font(BBFont.font(13, .bold))
                    .foregroundColor(BBColors.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
            }

            HStack(spacing: 12) {
                FocusInsight(
                    icon: "fork.knife",
                    label: "Macro focus",
                    value: macroFocusText,
                    color: BBColors.secondary
                )
                FocusInsight(
                    icon: "heart.fill",
                    label: "BMI status",
                    value: bmiText,
                    color: BBColors.danger
                )
            }

            HStack(spacing: 10) {
                MiniActionButton(icon: "map.fill", title: "Plan", color: BBColors.primary)
                MiniActionButton(icon: "target", title: "Goal", color: BBColors.secondary)
            }
        }
        .bbCard()
    }

    private var macroFocusText: String {
        guard let macro = payload.focus.macroFocus else { return "Set goal" }
        if macro.gap > 0 {
            return "\(macro.label) +\(Int(macro.gap.rounded()))g"
        }
        return macro.label
    }

    private var bmiText: String {
        guard let value = payload.bmi.value else { return "Needs info" }
        if let category = payload.bmi.category {
            return "\(String(format: "%.1f", value)) \(category)"
        }
        return String(format: "%.1f", value)
    }
}

private struct OverviewSideSummary: View {
    let payload: DashboardDayPayload

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            SectionHeader(icon: "person.text.rectangle.fill", title: "Daily summary", trailing: nil)

            HStack(spacing: 10) {
                MetricMiniBox(icon: "calendar", value: payload.selectedDate, label: "Date")
                MetricMiniBox(icon: "target", value: payload.calorieGoal.map { "\($0)" } ?? "--", label: "Target")
            }

            HStack(spacing: 10) {
                MetricMiniBox(icon: "scalemass.fill", value: payload.physical.weight.map { "\(Int($0))" } ?? "--", label: "kg")
                MetricMiniBox(icon: "ruler.fill", value: payload.physical.height.map { "\(Int($0))" } ?? "--", label: "cm")
                MetricMiniBox(icon: "gift.fill", value: "\(payload.streak.freezes)", label: "Freeze")
            }
        }
        .bbCard()
    }
}

// MARK: - Chart Components

private struct CalorieLineChart: View {
    let labels: [String]
    let values: [Int]

    var body: some View {
        GeometryReader { geo in
            let maxValue = max(Double(values.max() ?? 1), 1)
            let points = chartPoints(size: geo.size, maxValue: maxValue)

            ZStack(alignment: .bottomLeading) {
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .fill(BBColors.surfaceAlt)

                Path { path in
                    guard let first = points.first else { return }
                    path.move(to: first)
                    for point in points.dropFirst() {
                        path.addLine(to: point)
                    }
                }
                .stroke(BBColors.primary, style: StrokeStyle(lineWidth: 4, lineCap: .round, lineJoin: .round))
                .padding(.horizontal, 8)
                .padding(.vertical, 18)

                ForEach(Array(points.enumerated()), id: \.offset) { _, point in
                    Circle()
                        .fill(BBColors.surface)
                        .frame(width: 9, height: 9)
                        .overlay(Circle().stroke(BBColors.primary, lineWidth: 3))
                        .position(x: point.x + 8, y: point.y + 18)
                }

                HStack {
                    ForEach(labels.indices, id: \.self) { index in
                        Text(labels[index])
                            .font(.system(size: 10, weight: .bold))
                            .foregroundColor(BBColors.textSecondary)
                            .frame(maxWidth: .infinity)
                    }
                }
                .padding(.horizontal, 10)
                .padding(.bottom, 8)
            }
        }
    }

    private func chartPoints(size: CGSize, maxValue: Double) -> [CGPoint] {
        guard values.count > 1 else { return [] }
        let width = max(size.width - 16, 1)
        let height = max(size.height - 42, 1)
        return values.enumerated().map { index, value in
            let x = width * CGFloat(index) / CGFloat(max(values.count - 1, 1))
            let normalized = CGFloat(Double(value) / maxValue)
            let y = height * (1 - normalized)
            return CGPoint(x: x, y: y)
        }
    }
}

private struct MacroTrendChart: View {
    let history: DashboardHistory

    var body: some View {
        GeometryReader { geo in
            let maxValue = max(
                history.protein.max() ?? 0,
                history.carbs.max() ?? 0,
                history.fat.max() ?? 0,
                1
            )

            HStack(alignment: .bottom, spacing: 8) {
                ForEach(history.labels.indices, id: \.self) { index in
                    VStack(spacing: 6) {
                        HStack(alignment: .bottom, spacing: 3) {
                            macroBar(value: history.protein[safe: index] ?? 0, maxValue: maxValue, color: BBColors.primary, height: geo.size.height - 28)
                            macroBar(value: history.carbs[safe: index] ?? 0, maxValue: maxValue, color: BBColors.secondary, height: geo.size.height - 28)
                            macroBar(value: history.fat[safe: index] ?? 0, maxValue: maxValue, color: BBColors.accent, height: geo.size.height - 28)
                        }
                        Text(history.labels[index])
                            .font(.system(size: 9, weight: .bold))
                            .foregroundColor(BBColors.textSecondary)
                    }
                    .frame(maxWidth: .infinity)
                }
            }
        }
    }

    private func macroBar(value: Double, maxValue: Double, color: Color, height: CGFloat) -> some View {
        RoundedRectangle(cornerRadius: 5)
            .fill(color)
            .frame(width: 8, height: max(6, height * CGFloat(value / maxValue)))
    }
}

private struct WeightLineChart: View {
    let points: [DashboardWeightPoint]

    var body: some View {
        GeometryReader { geo in
            let values = points.map(\.weight)
            let minValue = values.min() ?? 0
            let maxValue = values.max() ?? 1
            let spread = max(maxValue - minValue, 1)
            let chartPoints = points.enumerated().map { index, point -> CGPoint in
                let width = max(geo.size.width - 16, 1)
                let height = max(geo.size.height - 34, 1)
                let x = width * CGFloat(index) / CGFloat(max(points.count - 1, 1))
                let y = height * (1 - CGFloat((point.weight - minValue) / spread))
                return CGPoint(x: x + 8, y: y + 12)
            }

            ZStack(alignment: .bottomLeading) {
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .fill(BBColors.surfaceAlt)

                if chartPoints.count > 1 {
                    Path { path in
                        path.move(to: chartPoints[0])
                        for point in chartPoints.dropFirst() {
                            path.addLine(to: point)
                        }
                    }
                    .stroke(BBColors.secondary, style: StrokeStyle(lineWidth: 4, lineCap: .round, lineJoin: .round))
                }

                ForEach(Array(chartPoints.enumerated()), id: \.offset) { _, point in
                    Circle()
                        .fill(BBColors.surface)
                        .frame(width: 9, height: 9)
                        .overlay(Circle().stroke(BBColors.secondary, lineWidth: 3))
                        .position(point)
                }

                if points.isEmpty {
                    Text("No weight logs yet")
                        .font(BBFont.font(13, .bold))
                        .foregroundColor(BBColors.textSecondary)
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                }
            }
        }
    }
}

private struct MealRingsView: View {
    let totalCalories: Int
    let meals: [MealSummary]
    let totals: [String: Int]

    var body: some View {
        ZStack {
            ForEach(Array(meals.enumerated()), id: \.element.id) { index, meal in
                let calories = totals[meal.key] ?? 0
                let progress = totalCalories > 0 ? Double(calories) / Double(totalCalories) : 0
                Circle()
                    .stroke(meal.color.opacity(0.12), lineWidth: 12)
                    .padding(CGFloat(index) * 18)
                Circle()
                    .trim(from: 0, to: CGFloat(min(max(progress, 0), 1)))
                    .stroke(meal.color, style: StrokeStyle(lineWidth: 12, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                    .padding(CGFloat(index) * 18)
            }

            VStack(spacing: 2) {
                Text("\(totalCalories)")
                    .font(.system(size: 34, weight: .black))
                    .foregroundColor(BBColors.text)
                Text("kcal")
                    .font(BBFont.font(12, .black))
                    .foregroundColor(BBColors.textSecondary)
            }
        }
        .padding(18)
        .background(BBColors.surfaceAlt)
        .cornerRadius(BBRadius.lg)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.lg).stroke(BBColors.border, lineWidth: 2))
    }
}

// MARK: - Small Shared Components

private struct MealSlotCard: View {
    let meal: MealSummary
    let calories: Int
    let entries: [IntakeEntry]

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("\(meal.emoji) \(meal.title)")
                    .font(BBFont.font(13, .black))
                    .foregroundColor(BBColors.text)
                    .lineLimit(1)
                    .minimumScaleFactor(0.78)
                Spacer()
                Text("\(calories)")
                    .font(BBFont.font(12, .black))
                    .foregroundColor(meal.color)
            }

            if let first = entries.first {
                Text(first.foodItem)
                    .font(BBFont.font(12, .bold))
                    .foregroundColor(BBColors.textSecondary)
                    .lineLimit(2)
            } else {
                HStack(spacing: 6) {
                    Image(systemName: "plus")
                    Text("Add food")
                }
                .font(BBFont.font(12, .black))
                .foregroundColor(meal.color)
            }
        }
        .padding(12)
        .frame(minHeight: 94, alignment: .top)
        .background(BBColors.surfaceAlt)
        .cornerRadius(BBRadius.md)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))
        .background(RoundedRectangle(cornerRadius: BBRadius.md).fill(BBColors.borderSubtle).offset(y: 4))
        .padding(.bottom, 4)
    }
}

private struct ProgressTrack: View {
    let value: Double
    let color: Color
    let height: CGFloat

    var body: some View {
        GeometryReader { geo in
            ZStack(alignment: .leading) {
                RoundedRectangle(cornerRadius: height / 2)
                    .fill(BBColors.surfaceAlt)
                RoundedRectangle(cornerRadius: height / 2)
                    .fill(color)
                    .frame(width: geo.size.width * CGFloat(min(max(value, 0) / 100, 1)))
            }
        }
        .frame(height: height)
    }
}

private struct SectionHeader: View {
    let icon: String
    let title: String
    let trailing: String?

    var body: some View {
        HStack(spacing: 8) {
            Image(systemName: icon)
                .font(BBFont.font(BBFont.sm, .black))
                .foregroundColor(BBColors.primary)
            Text(title)
                .font(BBFont.font(BBFont.base, .black))
                .foregroundColor(BBColors.text)
            Spacer()
            if let trailing {
                Text(trailing)
                    .font(BBFont.font(11, .black))
                    .foregroundColor(BBColors.textSecondary)
                    .padding(.horizontal, 9)
                    .padding(.vertical, 5)
                    .background(BBColors.surfaceAlt)
                    .cornerRadius(BBRadius.pill)
            }
        }
    }
}

private struct MacroLegend: View {
    var body: some View {
        HStack(spacing: 8) {
            legend(color: BBColors.primary, text: "Protein")
            legend(color: BBColors.secondary, text: "Carbs")
            legend(color: BBColors.accent, text: "Fat")
        }
    }

    private func legend(color: Color, text: String) -> some View {
        HStack(spacing: 5) {
            Circle().fill(color).frame(width: 8, height: 8)
            Text(text)
                .font(BBFont.font(11, .bold))
                .foregroundColor(BBColors.textSecondary)
        }
        .frame(maxWidth: .infinity)
    }
}

private struct TrendBadge: View {
    let diff: Double

    var body: some View {
        let color = diff > 0 ? BBColors.danger : (diff < 0 ? BBColors.primary : BBColors.textSecondary)
        HStack(spacing: 5) {
            Image(systemName: diff > 0 ? "arrow.up" : (diff < 0 ? "arrow.down" : "minus"))
            Text(String(format: "%.1f kg", abs(diff)))
        }
        .font(BBFont.font(12, .black))
        .foregroundColor(color)
        .padding(.horizontal, 10)
        .padding(.vertical, 7)
        .background(color.opacity(0.12))
        .cornerRadius(BBRadius.pill)
    }
}

private struct MiniActionButton: View {
    let icon: String
    let title: String
    let color: Color

    var body: some View {
        Button {} label: {
            Label(title, systemImage: icon)
                .font(BBFont.font(13, .black))
                .foregroundColor(.white)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 11)
        }
        .background(color)
        .cornerRadius(BBRadius.md)
        .background(RoundedRectangle(cornerRadius: BBRadius.md).fill(color.opacity(0.7)).offset(y: 4))
        .padding(.bottom, 4)
    }
}

private struct FocusInsight: View {
    let icon: String
    let label: String
    let value: String
    let color: Color

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Image(systemName: icon)
                .font(BBFont.font(BBFont.base, .black))
                .foregroundColor(color)
            Text(label)
                .font(.system(size: 10, weight: .black))
                .foregroundColor(BBColors.textSecondary)
            Text(value)
                .font(BBFont.font(13, .black))
                .foregroundColor(BBColors.text)
                .lineLimit(2)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(12)
        .background(BBColors.surfaceAlt)
        .cornerRadius(BBRadius.md)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))
    }
}

private struct MetricMiniBox: View {
    let icon: String
    let value: String
    let label: String

    var body: some View {
        VStack(spacing: 6) {
            Image(systemName: icon)
                .font(BBFont.font(BBFont.base, .black))
                .foregroundColor(BBColors.primary)
            Text(value)
                .font(BBFont.font(BBFont.lg, .black))
                .foregroundColor(BBColors.text)
                .lineLimit(1)
                .minimumScaleFactor(0.65)
            Text(label.uppercased())
                .font(BBFont.font(9, .black))
                .foregroundColor(BBColors.textSecondary)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 12)
        .background(BBColors.surfaceAlt)
        .cornerRadius(BBRadius.md)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))
    }
}

private struct DashboardLoadingCard: View {
    var body: some View {
        VStack(spacing: 14) {
            ProgressView()
                .tint(BBColors.primary)
            Text("Loading overview...")
                .font(BBFont.font(BBFont.sm, .bold))
                .foregroundColor(BBColors.textSecondary)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 36)
        .bbCard()
    }
}

private extension Array {
    subscript(safe index: Index) -> Element? {
        indices.contains(index) ? self[index] : nil
    }
}

// MARK: - Preview/Guest Fixtures

private extension DashboardDayPayload {
    static func empty(for date: Date) -> DashboardDayPayload {
        DashboardDayPayload(
            selectedDate: DashboardDateFormat.api.string(from: date),
            totalCalories: 0,
            calorieGoal: nil,
            progressPercentage: 0,
            statusClass: "unset",
            macros: MacroTotals(protein: 0, carbs: 0, fat: 0),
            macroGoals: MacroTotals(protein: 0, carbs: 0, fat: 0),
            history: DashboardHistory(labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"], calories: [0, 0, 0, 0, 0, 0, 0], protein: [0, 0, 0, 0, 0, 0, 0], carbs: [0, 0, 0, 0, 0, 0, 0], fat: [0, 0, 0, 0, 0, 0, 0]),
            averageCalories: nil,
            mealCategories: ["breakfast": 0, "lunch": 0, "dinner": 0, "snack": 0],
            entries: [],
            currentLevel: 1,
            totalXp: 0,
            xpIntoLevel: 0,
            xpForNext: 100,
            xpProgressPercentage: 0,
            streak: DashboardStreak(current: 0, longest: 0, freezes: 0, broken: 0),
            focus: DashboardFocusPayload(tone: "neutral", status: "setup", calorieRemaining: nil, calorieOverBy: nil, macroFocus: DashboardMacroFocus(key: "neutral", label: "Set goal", gap: 0, icon: "target")),
            bmi: DashboardBMI(value: nil, category: nil),
            physical: DashboardPhysicalPayload(age: nil, gender: nil, weight: nil, height: nil),
            weightHistory: []
        )
    }

    static func mock(for date: Date) -> DashboardDayPayload {
        let seed = Calendar.current.component(.day, from: date)
        let total = 1200 + ((seed * 73) % 900)
        let goal = 2200
        let progress = min(Double(total) / Double(goal) * 100, 100)
        let historyCalories = [1800, 2100, 1950, 2200, 2050, 1500, total]
        return DashboardDayPayload(
            selectedDate: DashboardDateFormat.api.string(from: date),
            totalCalories: total,
            calorieGoal: goal,
            progressPercentage: progress,
            statusClass: total > goal ? "overlimit" : "ongoing",
            macros: MacroTotals(protein: 85, carbs: 175, fat: 46),
            macroGoals: MacroTotals(protein: 165, carbs: 248, fat: 61),
            history: DashboardHistory(labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"], calories: historyCalories, protein: [110, 135, 125, 140, 130, 90, 85], carbs: [200, 250, 220, 260, 230, 180, 175], fat: [55, 62, 58, 65, 60, 48, 46]),
            averageCalories: Int(historyCalories.reduce(0, +) / historyCalories.count),
            mealCategories: ["breakfast": 450, "lunch": 550, "dinner": 250, "snack": 200],
            entries: [
                IntakeEntry(id: 1, foodItem: "Pho Bo", calories: 450, protein: 24, carbs: 50, fat: 12, mealCategory: "breakfast", dateIntake: nil, isoDate: nil),
                IntakeEntry(id: 2, foodItem: "Grilled Chicken Salad", calories: 550, protein: 42, carbs: 35, fat: 18, mealCategory: "lunch", dateIntake: nil, isoDate: nil),
                IntakeEntry(id: 3, foodItem: "Salmon & Rice", calories: 250, protein: 19, carbs: 30, fat: 8, mealCategory: "dinner", dateIntake: nil, isoDate: nil)
            ],
            currentLevel: 4,
            totalXp: 780,
            xpIntoLevel: 80,
            xpForNext: 200,
            xpProgressPercentage: 40,
            streak: DashboardStreak(current: 5, longest: 12, freezes: 1, broken: 0),
            focus: DashboardFocusPayload(tone: "good", status: "active", calorieRemaining: max(goal - total, 0), calorieOverBy: total > goal ? total - goal : nil, macroFocus: DashboardMacroFocus(key: "protein", label: "Protein", gap: 80, icon: "drumstick")),
            bmi: DashboardBMI(value: 22.9, category: "Normal"),
            physical: DashboardPhysicalPayload(age: 25, gender: "male", weight: 70, height: 175),
            weightHistory: [
                DashboardWeightPoint(id: 1, weight: 72.0, dateLogged: "2026-05-25", label: "25/05"),
                DashboardWeightPoint(id: 2, weight: 71.3, dateLogged: "2026-05-26", label: "26/05"),
                DashboardWeightPoint(id: 3, weight: 70.8, dateLogged: "2026-05-27", label: "27/05"),
                DashboardWeightPoint(id: 4, weight: 70.0, dateLogged: "2026-05-31", label: "31/05")
            ]
        )
    }
}

#Preview("Default") {
    DashboardView()
        .environmentObject(SessionStore.preview)
}
