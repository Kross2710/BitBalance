import SwiftUI

struct IntakeHistoryView: View {
    @EnvironmentObject private var session: SessionStore

    @State private var entries: [IntakeEntry] = []
    @State private var summary: DailySummary?
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var editingEntry: IntakeEntry?

    var body: some View {
        NavigationStack {
            ZStack {
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                List {
                    // Top Summary Card
                    if let summary {
                        VStack(alignment: .leading, spacing: 8) {
                            Text("TODAY'S SUMMARY")
                                .font(.system(size: 12, weight: .heavy))
                                .foregroundColor(BBColors.textSecondary)
                            
                            HStack {
                                VStack(alignment: .leading, spacing: 4) {
                                    Text("\(summary.totalCalories) kcal")
                                        .font(.system(size: 28, weight: .black))
                                        .foregroundColor(BBColors.text)
                                    
                                    if let goal = summary.calorieGoal {
                                        Text("\(Int(summary.progressPercentage))% of \(goal) kcal goal")
                                            .font(.system(size: 14, weight: .bold))
                                            .foregroundColor(BBColors.textSecondary)
                                    } else {
                                        Text("No calorie goal set")
                                            .font(.system(size: 14, weight: .bold))
                                            .foregroundColor(BBColors.textSecondary)
                                    }
                                }
                                Spacer()
                                Text("📊")
                                    .font(.system(size: 38))
                            }
                        }
                        .bbCard()
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
                        .font(.system(size: 14, weight: .bold))
                        .bbAlert(isSuccess: false)
                        .listRowSeparator(.hidden)
                        .listRowBackground(Color.clear)
                        .padding(.horizontal, 4)
                        .padding(.vertical, 4)
                    }

                    // Entries Header
                    if !entries.isEmpty {
                        Text("MEAL ENTRIES")
                            .font(.system(size: 12, weight: .heavy))
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
                                .font(.system(size: 16, weight: .bold))
                                .foregroundColor(BBColors.textSecondary)
                            Text("Add your meals using the Log tab!")
                                .font(.system(size: 13, weight: .medium))
                                .foregroundColor(BBColors.textMuted)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 32)
                        .listRowSeparator(.hidden)
                        .listRowBackground(Color.clear)
                    } else {
                        // Entries List
                        ForEach(entries) { entry in
                            Button {
                                editingEntry = entry
                            } label: {
                                IntakeEntryRow(entry: entry)
                            }
                            .listRowSeparator(.hidden)
                            .listRowBackground(Color.clear)
                            .padding(.horizontal, 4)
                            .padding(.vertical, 6)
                        }
                        .onDelete(perform: delete)
                    }
                }
                .listStyle(.plain)
            }
            .navigationTitle("History")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        Task {
                            await load()
                        }
                    } label: {
                        Image(systemName: "arrow.clockwise")
                            .font(.system(size: 15, weight: .bold))
                            .foregroundColor(BBColors.primary)
                    }
                }
            }
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

        do {
            let payload = try await session.loadIntakeHistory(limit: 50)
            entries = payload.entries
            summary = payload.dailySummary
        } catch {
            errorMessage = error.localizedDescription
        }

        isLoading = false
    }

    private func delete(at offsets: IndexSet) {
        for index in offsets {
            let entry = entries[index]
            Task {
                await delete(entry)
            }
        }
    }

    private func delete(_ entry: IntakeEntry) async {
        do {
            let payload = try await session.deleteIntake(id: entry.id)
            entries.removeAll { $0.id == payload.deletedId }
            summary = payload.dailySummary
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

// MARK: - Intake Entry Row View
private struct IntakeEntryRow: View {
    let entry: IntakeEntry

    var body: some View {
        HStack(spacing: 12) {
            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 8) {
                    Text(entry.foodItem)
                        .font(.system(size: 16, weight: .bold))
                        .foregroundColor(BBColors.text)
                    
                    // Colored Pill Badge
                    Text(entry.mealCategory.capitalized)
                        .font(.system(size: 10, weight: .black))
                        .padding(.vertical, 4)
                        .padding(.horizontal, 10)
                        .background(categoryColor.opacity(0.12))
                        .foregroundColor(categoryColor)
                        .cornerRadius(BBRadius.pill)
                        .overlay(
                            RoundedRectangle(cornerRadius: BBRadius.pill)
                                .stroke(categoryColor.opacity(0.3), lineWidth: 1)
                                .allowsHitTesting(false)
                        )
                }
                
                Text("P \(format(entry.protein))g  •  C \(format(entry.carbs))g  •  F \(format(entry.fat))g")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundColor(BBColors.textSecondary)
            }
            
            Spacer()
            
            VStack(alignment: .trailing, spacing: 2) {
                Text("\(entry.calories)")
                    .font(.system(size: 18, weight: .black))
                    .foregroundColor(BBColors.text)
                Text("kcal")
                    .font(.system(size: 10, weight: .heavy))
                    .foregroundColor(BBColors.textSecondary)
            }
        }
        .bbCard(radius: BBRadius.md, padding: 14)
    }

    private var categoryColor: Color {
        switch entry.mealCategory.lowercased() {
        case "breakfast": return BBColors.secondary
        case "lunch": return BBColors.accent
        case "dinner": return BBColors.primary
        default: return BBColors.danger
        }
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
                                .font(.system(size: 12, weight: .heavy))
                                .foregroundColor(BBColors.textSecondary)
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Food Name")
                                    .font(.system(size: 13, weight: .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("Food item", text: $foodItem)
                                    .textInputAutocapitalization(.words)
                                    .focused($focusedField, equals: .foodItem)
                                    .bbInput(isFocused: focusedField == .foodItem)
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Calories")
                                    .font(.system(size: 13, weight: .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("Calories", text: $calories)
                                    .keyboardType(.numberPad)
                                    .focused($focusedField, equals: .calories)
                                    .bbInput(isFocused: focusedField == .calories)
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Meal Category")
                                    .font(.system(size: 13, weight: .bold))
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
                                                        .font(.system(size: 14, weight: .bold))
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
                                .font(.system(size: 12, weight: .heavy))
                                .foregroundColor(BBColors.textSecondary)
                            
                            HStack(spacing: 12) {
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Protein (g)")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Protein", text: $protein)
                                        .keyboardType(.decimalPad)
                                        .focused($focusedField, equals: .protein)
                                        .bbInput(isFocused: focusedField == .protein)
                                }
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Carbs (g)")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Carbs", text: $carbs)
                                        .keyboardType(.decimalPad)
                                        .focused($focusedField, equals: .carbs)
                                        .bbInput(isFocused: focusedField == .carbs)
                                }
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Fat (g)")
                                    .font(.system(size: 13, weight: .bold))
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
                            .font(.system(size: 14, weight: .bold))
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
                    .font(.system(size: 15, weight: .bold))
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
