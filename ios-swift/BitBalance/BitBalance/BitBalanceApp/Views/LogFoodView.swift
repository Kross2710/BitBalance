import SwiftUI

struct LogFoodView: View {
    @EnvironmentObject private var session: SessionStore

    @State private var foodItem = ""
    @State private var calories = ""
    @State private var protein = ""
    @State private var carbs = ""
    @State private var fat = ""
    @State private var mealCategory = "breakfast"
    @State private var isSaving = false
    @State private var message: String?
    @State private var messageIsError = false
    @State private var showScanner = false
    @State private var isScanning = false

    @FocusState private var focusedField: Field?
    enum Field {
        case foodItem
        case calories
        case protein
        case carbs
        case fat
    }

    struct MealCategoryItem: Hashable, Identifiable {
        let id: String
        let name: String
        let emoji: String
    }

    private let categories = [
        MealCategoryItem(id: "breakfast", name: "Breakfast", emoji: "🌅"),
        MealCategoryItem(id: "lunch", name: "Lunch", emoji: "☀️"),
        MealCategoryItem(id: "dinner", name: "Dinner", emoji: "🌙"),
        MealCategoryItem(id: "snack", name: "Snack", emoji: "🍿")
    ]

    var body: some View {
        NavigationStack {
            ZStack {
                // Subtle brand background gradient
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                ScrollView {
                    VStack(alignment: .leading, spacing: 24) {
                        
                        // 1. Food Details Card Section
                        VStack(alignment: .leading, spacing: 18) {
                            HStack(spacing: 6) {
                                Text("🍽️")
                                    .font(BBFont.font(BBFont.base))
                                Text("FOOD DETAILS")
                                    .font(BBFont.font(BBFont.xs, .heavy))
                                    .foregroundColor(BBColors.textSecondary)
                            }
                            
                            // 3D tactile barcode scanner launcher
                            Button {
                                showScanner = true
                            } label: {
                                HStack(spacing: 8) {
                                    Image(systemName: "barcode.viewfinder")
                                        .font(BBFont.font(BBFont.base, .bold))
                                    Text("Scan Food Barcode")
                                        .font(BBFont.font(BBFont.sm, .black))
                                }
                                .foregroundColor(.white)
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 12)
                                .background(BBColors.secondary)
                                .cornerRadius(BBRadius.md)
                                .background(
                                    RoundedRectangle(cornerRadius: BBRadius.md)
                                        .fill(Color(hex: "0284C7"))
                                        .offset(y: 4)
                                )
                                .offset(y: -4)
                            }
                            .padding(.bottom, 2)
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Food Name")
                                    .font(BBFont.font(BBFont.sm, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("e.g. Avocado Toast", text: $foodItem)
                                    .textInputAutocapitalization(.words)
                                    .focused($focusedField, equals: .foodItem)
                                    .bbInput(isFocused: focusedField == .foodItem)
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Calories (kcal)")
                                    .font(BBFont.font(BBFont.sm, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("e.g. 350", text: $calories)
                                    .keyboardType(.numberPad)
                                    .focused($focusedField, equals: .calories)
                                    .bbInput(isFocused: focusedField == .calories)
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Meal Category")
                                    .font(BBFont.font(BBFont.sm, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                    .padding(.bottom, 2)
                                
                                ScrollView(.horizontal, showsIndicators: false) {
                                    HStack(spacing: 10) {
                                        ForEach(categories) { cat in
                                            CategoryButton(cat: cat, isSelected: mealCategory == cat.id) {
                                                mealCategory = cat.id
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
                        
                        // 2. Optional Macros Card Section
                        VStack(alignment: .leading, spacing: 18) {
                            HStack(spacing: 6) {
                                Text("📊")
                                    .font(BBFont.font(BBFont.base))
                                Text("NUTRITION MACROS (OPTIONAL)")
                                    .font(BBFont.font(BBFont.xs, .heavy))
                                    .foregroundColor(BBColors.textSecondary)
                            }
                            
                            HStack(spacing: 12) {
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Protein (g)")
                                        .font(BBFont.font(BBFont.sm, .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Protein", text: $protein)
                                        .keyboardType(.decimalPad)
                                        .focused($focusedField, equals: .protein)
                                        .bbInput(isFocused: focusedField == .protein)
                                }
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Carbs (g)")
                                        .font(BBFont.font(BBFont.sm, .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Carbs", text: $carbs)
                                        .keyboardType(.decimalPad)
                                        .focused($focusedField, equals: .carbs)
                                        .bbInput(isFocused: focusedField == .carbs)
                                }
                            }
                            
                            VStack(alignment: .leading, spacing: 6) {
                                Text("Fat (g)")
                                    .font(BBFont.font(BBFont.sm, .bold))
                                    .foregroundColor(BBColors.textSecondary)
                                TextField("Fat", text: $fat)
                                    .keyboardType(.decimalPad)
                                    .focused($focusedField, equals: .fat)
                                    .bbInput(isFocused: focusedField == .fat)
                            }
                        }
                        .bbCard()
                        
                        // Styled dynamic alert/status banner
                        if let message {
                            HStack(spacing: 10) {
                                Image(systemName: messageIsError ? "exclamationmark.triangle.fill" : "checkmark.circle.fill")
                                    .font(BBFont.font(BBFont.base, .bold))
                                Text(message)
                                    .font(BBFont.font(BBFont.sm, .bold))
                            }
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .bbAlert(isSuccess: !messageIsError)
                            .transition(.opacity.combined(with: .scale))
                        }
                        
                        // 3. Save Button
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
                                Text("Save Entry")
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
//            .navigationTitle("Log Food")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    if focusedField != nil {
                        Button("Done") {
                            focusedField = nil
                        }
                        .font(BBFont.font(BBFont.sm, .bold))
                        .foregroundColor(BBColors.primary)
                    }
                }
            }
            .sheet(isPresented: $showScanner) {
                BarcodeScannerView(onScan: { code in
                    scanBarcode(code)
                }, onCancel: {
                    showScanner = false
                })
                .ignoresSafeArea()
            }
        }
    }
    
    private func scanBarcode(_ code: String) {
        showScanner = false
        isScanning = true
        message = "Searching for barcode \(code)..."
        messageIsError = false
        
        Task {
            do {
                let product = try await session.lookupBarcode(barcode: code)
                isScanning = false
                
                if product.found {
                    foodItem = product.productName ?? ""
                    if let kcal = product.kcalPerServing {
                        calories = String(kcal)
                    }
                    if let prot = product.protein {
                        protein = format(prot)
                    }
                    if let crb = product.carbs {
                        carbs = format(crb)
                    }
                    if let ft = product.fat {
                        fat = format(ft)
                    }
                    show("Found: \(product.productName ?? "Product")", isError: false)
                } else {
                    show("Barcode not found.", isError: true)
                }
            } catch {
                isScanning = false
                show("Lookup failed: \(error.localizedDescription)", isError: true)
            }
        }
    }
    
    private func format(_ value: Double) -> String {
        if value.rounded() == value {
            return String(Int(value))
        }
        return String(format: "%.1f", value)
    }

    private var isValid: Bool {
        !foodItem.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty && Int(calories) ?? 0 > 0
    }

    private func save() async {
        guard let calorieValue = Int(calories), calorieValue > 0 else {
            show("Calories must be a positive number.", isError: true)
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
            _ = try await session.createIntake(payload)
            clearForm()
            show("Entry saved successfully!", isError: false)
        } catch {
            show(error.localizedDescription, isError: true)
        }
    }

    private func clearForm() {
        foodItem = ""
        calories = ""
        protein = ""
        carbs = ""
        fat = ""
        mealCategory = "breakfast"
    }

    private func show(_ text: String, isError: Bool) {
        message = text
        messageIsError = isError
        
        // Auto dismiss alert after 4 seconds
        Task {
            try? await Task.sleep(nanoseconds: 4_000_000_000)
            if message == text {
                withAnimation {
                    message = nil
                }
            }
        }
    }
}

// MARK: - Isolated Category Button Subview for Swift Compiler Optimization
private struct CategoryButton: View {
    let cat: LogFoodView.MealCategoryItem
    let isSelected: Bool
    let action: () -> Void
    
    var body: some View {
        Button(action: action) {
            HStack(spacing: 6) {
                Text(cat.emoji)
                Text(cat.name)
                    .font(BBFont.font(BBFont.sm, .bold))
            }
            .padding(.vertical, 10)
            .padding(.horizontal, 16)
            .background(isSelected ? BBColors.primary : BBColors.surfaceAlt)
            .foregroundColor(isSelected ? .white : BBColors.text)
            .cornerRadius(BBRadius.md)
            .overlay(
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .stroke(isSelected ? BBColors.primaryHover : BBColors.border, lineWidth: 2)
                    .allowsHitTesting(false)
            )
            .background(
                RoundedRectangle(cornerRadius: BBRadius.md)
                    .fill(isSelected ? BBColors.primaryHover : BBColors.borderSubtle)
                    .offset(y: isSelected ? 2 : 0)
            )
            .offset(y: isSelected ? -2 : 0)
        }
    }
}
