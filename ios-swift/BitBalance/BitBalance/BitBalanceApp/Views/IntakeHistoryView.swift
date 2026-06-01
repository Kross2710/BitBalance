import SwiftUI

struct IntakeHistoryView: View {
    @EnvironmentObject private var session: SessionStore

    @State private var entries: [IntakeEntry] = []
    @State private var summary: DailySummary?
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var editingEntry: IntakeEntry?
    @State private var currentPage = 1

    private let sectionsPerPage = 7

    private var groupedEntries: [HistoryEntrySection] {
        HistoryEntrySection.group(entries)
    }

    private var totalPages: Int {
        max(1, Int(ceil(Double(groupedEntries.count) / Double(sectionsPerPage))))
    }

    private var clampedPage: Int {
        min(max(currentPage, 1), totalPages)
    }

    private var pagedEntries: [HistoryEntrySection] {
        let start = (clampedPage - 1) * sectionsPerPage
        return Array(groupedEntries.dropFirst(start).prefix(sectionsPerPage))
    }

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                List {
                    // Top Summary Card
                    if let summary {
                        HistorySummaryCard(summary: summary)
                        .listRowSeparator(.hidden)
                        .listRowBackground(Color.clear)
                        .padding(.horizontal, 4)
                        .padding(.vertical, 6)
                    }

                    // Error Alert Banner
                    if let errorMessage {
                        HStack(spacing: 8) {
                            Image(systemName: "exclamationmark.triangle.fill")
                            Text(errorMessage)
                        }
                        .font(BBFont.font(BBFont.sm, .bold))
                        .bbAlert(isSuccess: false)
                        .listRowSeparator(.hidden)
                        .listRowBackground(Color.clear)
                        .padding(.horizontal, 4)
                        .padding(.vertical, 4)
                    }

                    // Entries Header
                    if !entries.isEmpty {
                        Text("MEAL TIMELINE")
                            .font(BBFont.font(BBFont.xs, .heavy))
                            .foregroundColor(BBColors.textSecondary)
                            .listRowSeparator(.hidden)
                            .listRowBackground(Color.clear)
                            .padding(.horizontal, 8)
                            .padding(.top, 8)
                            .padding(.bottom, 2)
                    }

                    // Loading State
                    if isLoading && entries.isEmpty {
                        HStack {
                            Spacer()
                            ProgressView()
                            Spacer()
                        }
                        .listRowSeparator(.hidden)
                        .listRowBackground(Color.clear)
                    } else if entries.isEmpty {
                        // Empty State
                        VStack(spacing: 12) {
                            Text("🍽️")
                                .font(.system(size: 48))
                            Text("No entries recorded yet.")
                                .font(BBFont.bodyBold)
                                .foregroundColor(BBColors.textSecondary)
                            Text("Add your meals using the Log tab!")
                                .font(BBFont.font(13, .medium))
                                .foregroundColor(BBColors.textMuted)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 32)
                        .listRowSeparator(.hidden)
                        .listRowBackground(Color.clear)
                    } else {
                        // Entries grouped by day/month/year.
                        ForEach(pagedEntries) { section in
                            HistoryDateSectionHeader(section: section)
                                .listRowSeparator(.hidden)
                                .listRowBackground(Color.clear)
                                .padding(.horizontal, 4)
                                .padding(.top, 4)
                                .padding(.bottom, 0)

                            ForEach(section.entries) { entry in
                                Button {
                                    editingEntry = entry
                                } label: {
                                    IntakeEntryRow(entry: entry)
                                }
                                .buttonStyle(.plain)
                                .swipeActions(edge: .trailing, allowsFullSwipe: true) {
                                    Button(role: .destructive) {
                                        Task {
                                            await delete(entry)
                                        }
                                    } label: {
                                        Label("Delete", systemImage: "trash.fill")
                                    }
                                }
                                .listRowSeparator(.hidden)
                                .listRowBackground(Color.clear)
                                .padding(.horizontal, 4)
                                .padding(.vertical, 3)
                            }
                        }

                        if totalPages > 1 {
                            HistoryPaginationBar(
                                currentPage: $currentPage,
                                totalPages: totalPages,
                                totalSections: groupedEntries.count,
                                sectionsPerPage: sectionsPerPage
                            )
                            .listRowSeparator(.hidden)
                            .listRowBackground(Color.clear)
                            .padding(.horizontal, 4)
                            .padding(.top, 10)
                            .padding(.bottom, 18)
                        }
                    }
                }
                .listStyle(.plain)
            }
//            .navigationTitle("History")

            .task {
                await load()
            }
            .refreshable {
                await load()
            }
            .sheet(item: $editingEntry) { entry in
                EditIntakeView(entry: entry) { updatedEntry, updatedSummary in
                    if let index = entries.firstIndex(where: { $0.id == updatedEntry.id }) {
                        entries[index] = updatedEntry
                    }
                    summary = updatedSummary
                    editingEntry = nil
                }
                .environmentObject(session)
            }
        }
    }

    private func load() async {
        isLoading = true
        errorMessage = nil

        if session.isGuest {
            applyMockHistory()
            isLoading = false
            return
        }

        do {
            let payload = try await session.loadIntakeHistory(limit: 100)
            entries = payload.entries
            summary = payload.dailySummary
            clampCurrentPage()
        } catch {
            if error.localizedDescription == "Authentication required." {
                applyMockHistory()
                errorMessage = nil
            } else {
                errorMessage = error.localizedDescription
            }
        }

        isLoading = false
    }

    private func delete(_ entry: IntakeEntry) async {
        do {
            let payload = try await session.deleteIntake(id: entry.id)
            entries.removeAll { $0.id == payload.deletedId }
            summary = payload.dailySummary
            clampCurrentPage()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func clampCurrentPage() {
        currentPage = min(max(currentPage, 1), totalPages)
    }

    private func applyMockHistory() {
        let mock = IntakeHistoryPayload.mock()
        entries = mock.entries
        summary = mock.dailySummary
        clampCurrentPage()
    }
}

// MARK: - Intake Entry Row View
private struct HistoryEntrySection: Identifiable {
    let id: String
    let date: Date?
    let entries: [IntakeEntry]

    var totalCalories: Int {
        entries.reduce(0) { $0 + $1.calories }
    }

    var entryCount: Int {
        entries.count
    }

    var dayNumber: String {
        guard let date else { return "--" }
        return Self.dayFormatter.string(from: date)
    }

    var monthYear: String {
        guard let date else { return "Unknown date" }
        return Self.monthYearFormatter.string(from: date)
    }

    var weekdayLabel: String {
        guard let date else { return "No date" }
        if Calendar.current.isDateInToday(date) {
            return "Today"
        }
        if Calendar.current.isDateInYesterday(date) {
            return "Yesterday"
        }
        return Self.weekdayFormatter.string(from: date)
    }

    static func group(_ entries: [IntakeEntry]) -> [HistoryEntrySection] {
        let grouped = Dictionary(grouping: entries) { entry in
            dateKey(for: entry)
        }

        return grouped
            .map { key, entries in
                HistoryEntrySection(
                    id: key,
                    date: date(from: key),
                    entries: entries.sorted { sortDate(for: $0) > sortDate(for: $1) }
                )
            }
            .sorted { lhs, rhs in
                (lhs.date ?? .distantPast) > (rhs.date ?? .distantPast)
            }
    }

    private static func dateKey(for entry: IntakeEntry) -> String {
        if let isoDate = entry.isoDate, isoDate.count >= 10 {
            return String(isoDate.prefix(10))
        }
        if let dateIntake = entry.dateIntake, dateIntake.count >= 10 {
            return String(dateIntake.prefix(10))
        }
        return "unknown"
    }

    private static func sortDate(for entry: IntakeEntry) -> Date {
        if let isoDate = entry.isoDate, let parsed = isoFormatter.date(from: isoDate) {
            return parsed
        }
        if let dateIntake = entry.dateIntake, let parsed = dbDateTimeFormatter.date(from: dateIntake) {
            return parsed
        }
        return .distantPast
    }

    private static func date(from key: String) -> Date? {
        dayKeyFormatter.date(from: key)
    }

    private static let isoFormatter = ISO8601DateFormatter()

    private static let dbDateTimeFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter
    }()

    private static let dayKeyFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter
    }()

    private static let dayFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "d"
        return formatter
    }()

    private static let monthYearFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "MMMM yyyy"
        return formatter
    }()

    private static let weekdayFormatter: DateFormatter = {
        let formatter = DateFormatter()
        formatter.dateFormat = "EEEE"
        return formatter
    }()
}

private extension IntakeHistoryPayload {
    static func mock() -> IntakeHistoryPayload {
        let calendar = Calendar.current
        let now = Date()
        var id = 1
        var entries: [IntakeEntry] = []

        let mealTemplates: [[(String, Int, Double, Double, Double, String)]] = [
            [
                ("Pho bo", 450, 24, 54, 13, "breakfast"),
                ("Grilled chicken rice bowl", 620, 46, 72, 14, "lunch"),
                ("Greek yogurt with berries", 180, 18, 22, 3, "snack")
            ],
            [
                ("Egg toast and avocado", 390, 20, 34, 19, "breakfast"),
                ("Salmon sushi set", 540, 32, 68, 16, "lunch"),
                ("Beef stir fry with noodles", 710, 38, 82, 24, "dinner")
            ],
            [
                ("Protein smoothie", 310, 35, 28, 8, "breakfast"),
                ("Chicken Caesar wrap", 520, 36, 44, 22, "lunch"),
                ("Apple and peanut butter", 240, 7, 28, 12, "snack")
            ],
            [
                ("Oatmeal banana bowl", 430, 16, 74, 9, "breakfast"),
                ("Turkey sandwich", 510, 34, 52, 17, "lunch"),
                ("Tofu curry with rice", 680, 30, 86, 21, "dinner")
            ]
        ]

        for dayOffset in 0..<11 {
            guard let day = calendar.date(byAdding: .day, value: -dayOffset, to: now) else {
                continue
            }

            let templates = mealTemplates[dayOffset % mealTemplates.count]
            for (index, item) in templates.enumerated() {
                let entryDate = calendar.date(bySettingHour: 8 + (index * 5), minute: index == 0 ? 15 : 30, second: 0, of: day) ?? day
                let dbDate = HistoryMockFormat.db.string(from: entryDate)
                let isoDate = HistoryMockFormat.iso.string(from: entryDate)

                entries.append(
                    IntakeEntry(
                        id: id,
                        foodItem: item.0,
                        calories: item.1,
                        protein: item.2,
                        carbs: item.3,
                        fat: item.4,
                        mealCategory: item.5,
                        dateIntake: dbDate,
                        isoDate: isoDate
                    )
                )
                id += 1
            }
        }

        let todayEntries = entries.filter { entry in
            guard let isoDate = entry.isoDate, let date = HistoryMockFormat.iso.date(from: isoDate) else {
                return false
            }
            return calendar.isDateInToday(date)
        }
        let totalCalories = todayEntries.reduce(0) { $0 + $1.calories }
        let macros = MacroTotals(
            protein: todayEntries.reduce(0) { $0 + $1.protein },
            carbs: todayEntries.reduce(0) { $0 + $1.carbs },
            fat: todayEntries.reduce(0) { $0 + $1.fat }
        )
        let goal = 2200

        return IntakeHistoryPayload(
            entries: entries,
            dailySummary: DailySummary(
                totalCalories: totalCalories,
                calorieGoal: goal,
                progressPercentage: min(Double(totalCalories) / Double(goal) * 100, 100),
                macros: macros,
                macroGoals: MacroTotals(protein: 165, carbs: 248, fat: 61)
            )
        )
    }
}

private enum HistoryMockFormat {
    static let db: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return formatter
    }()

    static let iso: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        return formatter
    }()
}

private struct HistoryDateSectionHeader: View {
    let section: HistoryEntrySection

    var body: some View {
        HStack(spacing: 12) {
            VStack(spacing: 0) {
                Text(section.dayNumber)
                    .font(.system(size: 26, weight: .black))
                    .foregroundColor(.white)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            .frame(width: 54, height: 54)
            .background(BBColors.primary)
            .cornerRadius(BBRadius.md)
            .overlay(
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .stroke(BBColors.primaryHover, lineWidth: 2)
            )
            .background(
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .fill(BBColors.primaryHover)
                    .offset(y: 4)
            )
            .padding(.bottom, 4)

            VStack(alignment: .leading, spacing: 3) {
                Text(section.weekdayLabel)
                    .font(BBFont.font(15, .black))
                    .foregroundColor(BBColors.text)
                Text(section.monthYear)
                    .font(BBFont.font(12, .bold))
                    .foregroundColor(BBColors.textSecondary)
            }

            Spacer()

            VStack(alignment: .trailing, spacing: 3) {
                Text("\(section.totalCalories) kcal")
                    .font(BBFont.font(13, .black))
                    .foregroundColor(BBColors.text)
                Text("\(section.entryCount) \(section.entryCount == 1 ? "entry" : "entries")")
                    .font(.system(size: 11, weight: .bold))
                    .foregroundColor(BBColors.textSecondary)
            }
        }
        .padding(14)
        .background(BBColors.surface)
        .cornerRadius(BBRadius.lg)
        .overlay(
            RoundedRectangle(cornerRadius: BBRadius.lg)
                .stroke(BBColors.border, lineWidth: 2)
        )
        .background(
            RoundedRectangle(cornerRadius: BBRadius.lg)
                .fill(BBColors.borderSubtle)
                .offset(y: 4)
        )
        .padding(.bottom, 4)
    }
}

private struct HistoryPaginationBar: View {
    @Binding var currentPage: Int
    let totalPages: Int
    let totalSections: Int
    let sectionsPerPage: Int

    private var firstVisibleDayIndex: Int {
        ((currentPage - 1) * sectionsPerPage) + 1
    }

    private var lastVisibleDayIndex: Int {
        min(currentPage * sectionsPerPage, totalSections)
    }

    var body: some View {
        VStack(spacing: 12) {
            HStack(spacing: 10) {
                pageNavButton(title: "Previous", systemImage: "chevron.left", disabled: currentPage <= 1) {
                    currentPage = max(1, currentPage - 1)
                }

                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(1...totalPages, id: \.self) { page in
                            Button {
                                currentPage = page
                            } label: {
                                Text("\(page)")
                                    .font(BBFont.font(13, .black))
                                    .foregroundColor(currentPage == page ? .white : BBColors.text)
                                    .frame(width: 36, height: 36)
                                    .background(currentPage == page ? BBColors.primary : BBColors.surfaceAlt)
                                    .cornerRadius(10)
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 10)
                                            .stroke(currentPage == page ? BBColors.primaryHover : BBColors.border, lineWidth: 2)
                                    )
                                    .background(
                                        RoundedRectangle(cornerRadius: 10)
                                            .fill(currentPage == page ? BBColors.primaryHover : BBColors.borderSubtle)
                                            .offset(y: currentPage == page ? 3 : 2)
                                    )
                                    .padding(.bottom, 3)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(.vertical, 2)
                }
                .frame(maxWidth: .infinity)

                pageNavButton(title: "Next", systemImage: "chevron.right", disabled: currentPage >= totalPages) {
                    currentPage = min(totalPages, currentPage + 1)
                }
            }

            Text("Showing days \(firstVisibleDayIndex)-\(lastVisibleDayIndex) of \(totalSections)")
                .font(.system(size: 11, weight: .bold))
                .foregroundColor(BBColors.textSecondary)
        }
        .padding(14)
        .background(BBColors.surface)
        .cornerRadius(BBRadius.lg)
        .overlay(
            RoundedRectangle(cornerRadius: BBRadius.lg)
                .stroke(BBColors.border, lineWidth: 2)
        )
        .background(
            RoundedRectangle(cornerRadius: BBRadius.lg)
                .fill(BBColors.borderSubtle)
                .offset(y: 5)
        )
        .padding(.bottom, 5)
    }

    private func pageNavButton(title: String, systemImage: String, disabled: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: systemImage)
                .font(BBFont.font(12, .black))
                .foregroundColor(disabled ? BBColors.textMuted : .white)
                .frame(width: 38, height: 38)
                .background(disabled ? BBColors.surfaceAlt : BBColors.secondary)
                .cornerRadius(10)
                .overlay(
                    RoundedRectangle(cornerRadius: 10)
                        .stroke(disabled ? BBColors.border : BBColors.secondary.opacity(0.85), lineWidth: 2)
                )
                .accessibilityLabel(title)
        }
        .buttonStyle(.plain)
        .disabled(disabled)
        .opacity(disabled ? 0.55 : 1)
    }
}

private struct HistorySummaryCard: View {
    let summary: DailySummary

    private var statusColor: Color {
        if let goal = summary.calorieGoal, summary.totalCalories > goal {
            return BBColors.danger
        }
        return BBColors.primary
    }

    private var statusText: String {
        guard let goal = summary.calorieGoal, goal > 0 else {
            return "Unset"
        }
        return summary.totalCalories > goal ? "Overlimit" : "Ongoing"
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(alignment: .firstTextBaseline) {
                Text("TODAY'S SUMMARY")
                    .font(BBFont.font(BBFont.xs, .heavy))
                    .foregroundColor(BBColors.textSecondary)
                Spacer()
                Text(statusText.uppercased())
                    .font(.system(size: 11, weight: .black))
                    .foregroundColor(statusColor)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 5)
                    .background(statusColor.opacity(0.12))
                    .cornerRadius(BBRadius.pill)
            }

            HStack(alignment: .firstTextBaseline, spacing: 4) {
                Text("\(summary.totalCalories)")
                    .font(.system(size: 40, weight: .black))
                    .foregroundColor(statusColor)
                Text("kcal")
                    .font(BBFont.font(15, .bold))
                    .foregroundColor(BBColors.textSecondary)
                Spacer()
            }

            HistoryProgressTrack(value: summary.progressPercentage, color: statusColor)

            HStack {
                Text(summary.calorieGoal.map { "Goal \($0) kcal" } ?? "No calorie goal set")
                    .font(BBFont.font(13, .bold))
                    .foregroundColor(BBColors.textSecondary)
                Spacer()
                Text("\(Int(min(max(summary.progressPercentage, 0), 999)))%")
                    .font(BBFont.font(13, .black))
                    .foregroundColor(statusColor)
            }
        }
        .bbCard()
    }
}

private struct HistoryProgressTrack: View {
    let value: Double
    let color: Color

    var body: some View {
        GeometryReader { geo in
            ZStack(alignment: .leading) {
                RoundedRectangle(cornerRadius: 7)
                    .fill(BBColors.surfaceAlt)
                RoundedRectangle(cornerRadius: 7)
                    .fill(color)
                    .frame(width: geo.size.width * CGFloat(min(max(value, 0) / 100, 1)))
            }
        }
        .frame(height: 14)
    }
}

private struct IntakeEntryRow: View {
    let entry: IntakeEntry

    var body: some View {
        VStack(alignment: .leading, spacing: 9) {
            HStack(alignment: .top, spacing: 10) {
                Text(categoryIcon)
                    .font(BBFont.font(BBFont.lg, .regular))
                    .frame(width: 38, height: 38)
                    .background(categoryColor.opacity(0.12))
                    .cornerRadius(12)
                    .overlay(
                        RoundedRectangle(cornerRadius: 12)
                            .stroke(categoryColor.opacity(0.28), lineWidth: 1)
                    )

                VStack(alignment: .leading, spacing: 4) {
                    Text(entry.foodItem)
                        .font(BBFont.font(BBFont.base, .black))
                        .foregroundColor(BBColors.text)
                        .lineLimit(2)
                        .multilineTextAlignment(.leading)
                        .fixedSize(horizontal: false, vertical: true)

                    Text(entry.mealCategory.capitalized)
                        .font(.system(size: 10, weight: .black))
                        .foregroundColor(categoryColor)
                        .padding(.vertical, 3)
                        .padding(.horizontal, 8)
                        .background(categoryColor.opacity(0.12))
                        .cornerRadius(BBRadius.pill)
                        .overlay(
                            RoundedRectangle(cornerRadius: BBRadius.pill)
                                .stroke(categoryColor.opacity(0.3), lineWidth: 1)
                        )
                }

                Spacer(minLength: 8)

                VStack(alignment: .trailing, spacing: 2) {
                    Text("\(entry.calories)")
                        .font(BBFont.font(22, .black))
                        .foregroundColor(BBColors.text)
                    Text("kcal")
                        .font(.system(size: 10, weight: .heavy))
                        .foregroundColor(BBColors.textSecondary)
                }
            }

            HStack(spacing: 7) {
                MacroChip(label: "P", value: entry.protein, color: BBColors.primary)
                MacroChip(label: "C", value: entry.carbs, color: BBColors.secondary)
                MacroChip(label: "F", value: entry.fat, color: BBColors.accent)
            }
        }
        .bbCard(radius: BBRadius.md, padding: 12)
    }

    private var categoryIcon: String {
        switch entry.mealCategory.lowercased() {
        case "breakfast": return "🌅"
        case "lunch": return "☀️"
        case "dinner": return "🌙"
        case "snack": return "🍿"
        default: return "🍽️"
        }
    }

    private var categoryColor: Color {
        switch entry.mealCategory.lowercased() {
        case "breakfast": return BBColors.secondary
        case "lunch": return BBColors.accent
        case "dinner": return BBColors.primary
        default: return BBColors.danger
        }
    }
}

private struct MacroChip: View {
    let label: String
    let value: Double
    let color: Color

    var body: some View {
        HStack(spacing: 4) {
            Text(label)
                .font(.system(size: 10, weight: .black))
                .foregroundColor(color)
            Text("\(format(value))g")
                .font(.system(size: 11, weight: .black))
                .foregroundColor(BBColors.text)
                .lineLimit(1)
                .minimumScaleFactor(0.75)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 7)
        .padding(.horizontal, 8)
        .background(BBColors.surfaceAlt)
        .cornerRadius(10)
        .overlay(
            RoundedRectangle(cornerRadius: 10)
                .stroke(BBColors.border, lineWidth: 2)
        )
    }

    private func format(_ value: Double) -> String {
        if value.rounded() == value {
            return String(Int(value))
        }
        return String(format: "%.1f", value)
    }
}

// MARK: - Edit Intake View Sheet
private struct EditIntakeView: View {
    @EnvironmentObject private var session: SessionStore
    @Environment(\.dismiss) private var dismiss

    let entry: IntakeEntry
    let onSaved: (IntakeEntry, DailySummary) -> Void

    @State private var foodItem: String
    @State private var calories: String
    @State private var protein: String
    @State private var carbs: String
    @State private var fat: String
    @State private var mealCategory: String
    @State private var isSaving = false
    @State private var errorMessage: String?
    
    @FocusState private var focusedField: Field?
    enum Field {
        case foodItem
        case calories
        case protein
        case carbs
        case fat
    }

    private let categories = [
        ("breakfast", "Breakfast"),
        ("lunch", "Lunch"),
        ("dinner", "Dinner"),
        ("snack", "Snack")
    ]

    init(entry: IntakeEntry, onSaved: @escaping (IntakeEntry, DailySummary) -> Void) {
        self.entry = entry
        self.onSaved = onSaved
        _foodItem = State(initialValue: entry.foodItem)
        _calories = State(initialValue: String(entry.calories))
        _protein = State(initialValue: Self.format(entry.protein))
        _carbs = State(initialValue: Self.format(entry.carbs))
        _fat = State(initialValue: Self.format(entry.fat))
        _mealCategory = State(initialValue: entry.mealCategory)
    }

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                ScrollView {
                    VStack(alignment: .leading, spacing: 24) {
                        
                        // Food Section Card
                        VStack(alignment: .leading, spacing: 18) {
                            Text("FOOD DETAILS")
                                .font(BBFont.font(BBFont.xs, .heavy))
                                .foregroundColor(BBColors.textSecondary)
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Food Name")
                                    .font(BBFont.font(13, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("Food item", text: $foodItem)
                                    .textInputAutocapitalization(.words)
                                    .focused($focusedField, equals: .foodItem)
                                    .bbInput(isFocused: focusedField == .foodItem)
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Calories")
                                    .font(BBFont.font(13, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("Calories", text: $calories)
                                    .keyboardType(.numberPad)
                                    .focused($focusedField, equals: .calories)
                                    .bbInput(isFocused: focusedField == .calories)
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Meal Category")
                                    .font(BBFont.font(13, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                    .padding(.bottom, 2)
                                
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 8) {
                                        ForEach(categories, id: \.0) { cat in
                                            let isSelected = mealCategory == cat.0
                                            let emoji = cat.0 == "breakfast" ? "🌅" : cat.0 == "lunch" ? "☀️" : cat.0 == "dinner" ? "🌙" : "🍿"
                                            Button {
                                                mealCategory = cat.0
                                            } label: {
                                                HStack(spacing: 6) {
                                                    Text(emoji)
                                                    Text(cat.1)
                                                        .font(BBFont.font(BBFont.sm, .bold))
                                                }
                                                .padding(.vertical, 8)
                                                .padding(.horizontal, 12)
                                                .background(isSelected ? BBColors.primary : BBColors.surfaceAlt)
                                                .foregroundColor(isSelected ? .white : BBColors.text)
                                                .cornerRadius(BBRadius.md)
                                                .overlay(
                                                    RoundedRectangle(cornerRadius: BBRadius.md)
                                                        .stroke(isSelected ? BBColors.primaryHover : BBColors.border, lineWidth: 2)
                                                )
                                                .background(
                                                    RoundedRectangle(cornerRadius: BBRadius.md)
                                                        .fill(isSelected ? BBColors.primaryHover : BBColors.borderSubtle)
                                                        .offset(y: isSelected ? 2 : 0)
                                                )
                                                .offset(y: isSelected ? -2 : 0)
                                            }
                                            .animation(.interactiveSpring(response: 0.15, dampingFraction: 0.8, blendDuration: 0), value: mealCategory)
                                        }
                                    }
                                    .padding(.vertical, 4)
                                    .padding(.horizontal, 2)
                                }
                            }
                        }
                        .bbCard()
                        
                        // Macros Section Card
                        VStack(alignment: .leading, spacing: 18) {
                            Text("NUTRITION MACROS")
                                .font(BBFont.font(BBFont.xs, .heavy))
                                .foregroundColor(BBColors.textSecondary)
                            
                            HStack(spacing: 12) {
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Protein (g)")
                                        .font(BBFont.font(13, .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Protein", text: $protein)
                                        .keyboardType(.decimalPad)
                                        .focused($focusedField, equals: .protein)
                                        .bbInput(isFocused: focusedField == .protein)
                                }
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Carbs (g)")
                                        .font(BBFont.font(13, .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Carbs", text: $carbs)
                                        .keyboardType(.decimalPad)
                                        .focused($focusedField, equals: .carbs)
                                        .bbInput(isFocused: focusedField == .carbs)
                                }
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Fat (g)")
                                    .font(BBFont.font(13, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("Fat", text: $fat)
                                    .keyboardType(.decimalPad)
                                    .focused($focusedField, equals: .fat)
                                    .bbInput(isFocused: focusedField == .fat)
                            }
                        }
                        .bbCard()
                        
                        if let errorMessage {
                            HStack(spacing: 8) {
                                Image(systemName: "exclamationmark.triangle.fill")
                                Text(errorMessage)
                            }
                            .font(BBFont.font(BBFont.sm, .bold))
                            .bbAlert(isSuccess: false)
                        }
                        
                        // Bouncy Save button
                        Button {
                            focusedField = nil
                            Task {
                                await save()
                            }
                        } label: {
                            if isSaving {
                                ProgressView()
                                    .tint(.white)
                                    .frame(maxWidth: .infinity)
                            } else {
                                Text("Save Changes")
                                    .frame(maxWidth: .infinity)
                            }
                        }
                        .buttonStyle(BBButtonStyle(
                            backgroundColor: BBColors.primary,
                            shadowColor: BBColors.primaryHover,
                            isEnabled: isValid && !isSaving
                        ))
                        .disabled(isSaving || !isValid)
                        .padding(.top, 4)
                        .padding(.bottom, 24)
                    }
                    .padding(20)
                }
            }
            .navigationTitle("Edit Entry")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        dismiss()
                    }
                    .font(BBFont.font(15, .bold))
                    .foregroundColor(BBColors.textSecondary)
                }
            }
        }
    }

    private var isValid: Bool {
        !foodItem.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && Int(calories) ?? 0 > 0
    }

    private func save() async {
        guard let calorieValue = Int(calories), calorieValue > 0 else {
            errorMessage = "Calories must be a positive number."
            return
        }

        isSaving = true
        defer {
            isSaving = false
        }

        let payload = IntakeFormPayload(
            foodItem: foodItem.trimmingCharacters(in: .whitespacesAndNewlines),
            calories: calorieValue,
            protein: Double(protein) ?? 0,
            carbs: Double(carbs) ?? 0,
            fat: Double(fat) ?? 0,
            mealCategory: mealCategory
        )

        do {
            let response = try await session.updateIntake(id: entry.id, payload: payload)
            if let updatedEntry = response.entry {
                onSaved(updatedEntry, response.dailySummary)
            } else {
                errorMessage = "Server did not return the updated entry."
            }
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private static func format(_ value: Double) -> String {
        if value.rounded() == value {
            return String(Int(value))
        }
        return String(format: "%.1f", value)
    }
}

#Preview("Default") {
    IntakeHistoryView()
        .environmentObject(SessionStore.preview)
}
