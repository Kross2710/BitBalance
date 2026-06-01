import SwiftUI

// MARK: - Onboarding Wizard
// Mirrors dashboard/set-goal.php (6-7 input steps + loading + overview).
// TDEE calculation is done client-side using the same formulas as
// dashboard/handlers/goal_plan.php so the overview renders instantly offline.
// The final commit POSTs to api/onboarding/save.php to persist in DB.

struct OnboardingView: View {
    @EnvironmentObject private var session: SessionStore

    // ── Wizard state ──────────────────────────────────────────────────────
    @State private var step: OnboardingStep = .gender
    @State private var gender: String    = "male"
    @State private var age: Int          = 25
    @State private var heightCm: Int     = 170
    @State private var weightKg: Int     = 65
    @State private var activity: String  = "moderately_active"
    @State private var goalMode: String  = "lose"
    @State private var weeklyRate: Double = 0.5
    @State private var targetWeight: String = ""
    @State private var plan: PersonalPlan? = nil
    @State private var loadingChecks: [Bool] = Array(repeating: false, count: 5)
    @State private var isSaving = false
    @State private var errorMessage: String? = nil

    // steps that count toward the "X/Y" progress counter
    private var inputSteps: [OnboardingStep] {
        let base: [OnboardingStep] = [.gender, .age, .height, .weight, .activity, .goal]
        return (goalMode == "maintain") ? base : base + [.pace]
    }

    private var progressFraction: Double {
        if step == .loading {
            return 0.99
        }
        if step == .overview {
            return 1.0
        }
        guard let idx = inputSteps.firstIndex(of: step) else { return 1.0 }
        return Double(idx + 1) / Double(inputSteps.count)
    }

    private var progressLabel: String {
        if step == .loading {
            return "99%"
        }
        if step == .overview {
            return "100%"
        }
        if let idx = inputSteps.firstIndex(of: step) {
            return "\(idx + 1)/\(inputSteps.count)"
        }
        return "100%"
    }

    private var progressTitle: String {
        if step == .loading {
            return "Calculating"
        }
        if step == .overview {
            return "Plan ready"
        }
        return "Personalize"
    }

    var body: some View {
        ZStack {
            BBColors.backgroundGradient.ignoresSafeArea()

            VStack(spacing: 0) {
                topBar
                stepBody
            }
        }
        .animation(.easeInOut(duration: 0.22), value: step)
    }

    // MARK: - Top bar (progress track + back button)

    private var topBar: some View {
        HStack(spacing: 12) {
            // back button
            Button {
                goBack()
            } label: {
                Image(systemName: "chevron.left")
                    .font(BBFont.bodyBold)
                    .foregroundColor(BBColors.text)
                    .frame(width: 44, height: 44)
                    .background(BBColors.surfaceAlt)
                    .cornerRadius(BBRadius.md)
                    .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))
                    .background(RoundedRectangle(cornerRadius: BBRadius.md).fill(BBColors.borderSubtle).offset(y: 4))
            }
            .opacity((step == .gender || step == .loading) ? 0 : 1)
            .disabled(step == .gender || step == .loading)

            VStack(spacing: 6) {
                HStack {
                    Text(progressTitle)
                        .font(BBFont.font(13, .heavy))
                        .foregroundColor(BBColors.textSecondary)
                    Spacer()
                    Text(progressLabel)
                        .font(BBFont.font(13, .heavy))
                        .foregroundColor(BBColors.primary)
                }
                GeometryReader { geo in
                    ZStack(alignment: .leading) {
                        RoundedRectangle(cornerRadius: BBRadius.pill)
                            .fill(BBColors.surfaceAlt)
                            .overlay(RoundedRectangle(cornerRadius: BBRadius.pill).stroke(BBColors.border, lineWidth: 2))
                        RoundedRectangle(cornerRadius: BBRadius.pill)
                            .fill(LinearGradient(
                                gradient: Gradient(colors: [BBColors.primary, BBColors.primaryHover]),
                                startPoint: .leading, endPoint: .trailing))
                            .frame(width: geo.size.width * progressFraction)
                            .animation(.spring(response: 0.45), value: progressFraction)
                    }
                    .frame(height: 14)
                }
                .frame(height: 14)
            }
        }
        .padding(.horizontal, 18)
        .padding(.top, 16)
        .padding(.bottom, 8)
    }

    // MARK: - Step router

    @ViewBuilder
    private var stepBody: some View {
        switch step {
        case .gender:   genderStep
        case .age:      pickerStep(kicker: "Body metrics", title: "What is your age?", range: 13...100, selection: $age, unit: "years")
        case .height:   pickerStep(kicker: "Body metrics", title: "How tall are you?", range: 100...250, selection: $heightCm, unit: "cm")
        case .weight:   pickerStep(kicker: "Body metrics", title: "What is your weight?", range: 30...300, selection: $weightKg, unit: "kg")
        case .activity: activityStep
        case .goal:     goalStep
        case .pace:     paceStep
        case .loading:  loadingStep
        case .overview: overviewStep
        }
    }

    // MARK: - Step: Gender

    private var genderStep: some View {
        WizardCard(kicker: "Welcome, \(session.user?.firstName ?? "there")", title: "Who are you?",
                   subtitle: "BitBalance uses this to estimate your daily energy burn.") {
            HStack(spacing: 12) {
                ForEach(genderOptions, id: \.id) { opt in
                    ChoiceCard(icon: opt.icon, label: opt.label, isSelected: gender == opt.id) {
                        gender = opt.id
                    }
                }
            }
            .padding(.bottom, 8)
        } footer: {
            wizardPrimary(goalMode == "maintain" ? "Get my personal plan" : "Continue") { advance() }
        }
    }

    private let genderOptions: [(id: String, icon: String, label: String)] = [
        ("male",   "person.fill", "Male"),
        ("female", "person.fill", "Female"),
        ("other",  "person.fill",  "Other")
    ]

    // MARK: - Step: Picker (age / height / weight)

    private func pickerStep(kicker: String, title: String, range: ClosedRange<Int>,
                            selection: Binding<Int>, unit: String) -> some View {
        WizardCard(kicker: kicker, title: title, subtitle: "Scroll to your \(unit == "years" ? "age" : unit).") {
            Picker("", selection: selection) {
                ForEach(range, id: \.self) { val in
                    Text("\(val) \(unit)").tag(val)
                }
            }
            .pickerStyle(.wheel)
            .frame(height: 200)
        } footer: {
            wizardPrimary("Continue") { advance() }
        }
    }

    // MARK: - Step: Activity

    private var activityStep: some View {
        WizardCard(kicker: "Daily routine", title: "Activity level",
                   subtitle: "Choose the option that looks most like a normal week.") {
            VStack(spacing: 10) {
                ForEach(activityOptions, id: \.id) { opt in
                    WideChoiceCard(icon: opt.icon, label: opt.label, detail: opt.detail, isSelected: activity == opt.id) {
                        activity = opt.id
                    }
                }
            }
        } footer: {
            wizardPrimary("Continue") { advance() }
        }
    }

    private let activityOptions: [(id: String, icon: String, label: String, detail: String)] = [
        ("sedentary",        "couch.fill", "Sedentary",         "Little or no exercise"),
        ("lightly_active",   "figure.walk", "Lightly active",    "Light exercise 1-3 days/week"),
        ("moderately_active","figure.run", "Moderately active", "Exercise 3-5 days/week"),
        ("very_active",      "dumbbell.fill", "Very active",       "Hard exercise 6-7 days/week"),
        ("extra_active",     "bolt.fill", "Extra active",      "Physical job or intense training")
    ]

    // MARK: - Step: Goal

    private var goalStep: some View {
        WizardCard(kicker: "Goal setup", title: "What is your goal?",
                   subtitle: "This sets the calorie target we will use on your dashboard.") {
            HStack(spacing: 12) {
                ForEach(goalOptions, id: \.id) { opt in
                    ChoiceCard(icon: opt.icon, label: opt.label, detail: opt.detail, isSelected: goalMode == opt.id) {
                        goalMode = opt.id
                        if goalMode == "maintain" { weeklyRate = 0 }
                    }
                }
            }
        } footer: {
            wizardPrimary("Continue") { advance() }
        }
    }

    private let goalOptions: [(id: String, icon: String, label: String, detail: String)] = [
        ("lose",     "arrow.down.right.circle.fill", "Lose",     "Calorie deficit"),
        ("maintain", "scalemass.fill", "Maintain", "Hold steady"),
        ("gain",     "arrow.up.forward.circle.fill", "Gain",     "Calorie surplus")
    ]

    // MARK: - Step: Pace

    private var paceStep: some View {
        WizardCard(kicker: "Goal pace", title: "How fast?",
                   subtitle: goalMode == "lose" ? "Pick a weekly loss pace." : "Pick a weekly gain pace.") {
            HStack(spacing: 12) {
                ForEach(paceOptions, id: \.rate) { opt in
                    ChoiceCard(icon: opt.icon, label: opt.label, detail: opt.detail, isSelected: weeklyRate == opt.rate) {
                        weeklyRate = opt.rate
                    }
                }
            }
            .padding(.bottom, 4)

            VStack(alignment: .leading, spacing: 6) {
                Text("Target weight (optional)")
                    .font(BBFont.font(13, .bold))
                    .foregroundColor(BBColors.textSecondary)
                TextField("e.g. 60 kg", text: $targetWeight)
                    .keyboardType(.decimalPad)
                    .bbInput()
            }
        } footer: {
            wizardPrimary("Get my personal plan") {
                withAnimation { step = .loading }
                buildPlan()
            }
        }
    }

    private let paceOptions: [(rate: Double, icon: String, label: String, detail: String)] = [
        (0.25, "leaf.fill", "Gentle", "0.25 kg/wk"),
        (0.50, "bolt.fill", "Steady", "0.50 kg/wk"),
        (0.75, "flame.fill", "Fast",   "0.75 kg/wk")
    ]

    // MARK: - Step: Loading

    private var loadingStep: some View {
        VStack(spacing: 0) {
            Spacer()
            // Progress ring
            ZStack {
                Circle()
                    .stroke(BBColors.border, lineWidth: 4)
                    .frame(width: 160, height: 160)
                Circle()
                    .trim(from: 0, to: loadingChecks.filter { $0 }.count == 0 ? 0.05 :
                          CGFloat(loadingChecks.filter { $0 }.count) / CGFloat(loadingChecks.count))
                    .stroke(BBColors.primary, style: StrokeStyle(lineWidth: 8, lineCap: .round))
                    .frame(width: 160, height: 160)
                    .rotationEffect(.degrees(-90))
                    .animation(.easeInOut(duration: 0.5), value: loadingChecks.filter { $0 }.count)
                if loadingChecks.filter { $0 }.count == loadingChecks.count {
                    Image(systemName: "checkmark")
                        .font(.system(size: 36, weight: .heavy))
                        .foregroundColor(BBColors.primary)
                } else {
                    Text("\(Int(Double(loadingChecks.filter { $0 }.count) / Double(loadingChecks.count) * 100))%")
                        .font(.system(size: 36, weight: .heavy))
                        .foregroundColor(BBColors.text)
                }
            }
            .padding(.bottom, 28)

            Text("AI is calculating...")
                .font(.system(size: 26, weight: .heavy))
                .foregroundColor(BBColors.text)
                .padding(.bottom, 8)
            Text("Building a personalized plan for you...")
                .font(BBFont.font(BBFont.base, .medium))
                .foregroundColor(BBColors.textSecondary)
                .padding(.bottom, 32)

            VStack(spacing: 14) {
                ForEach(loadingItems.indices, id: \.self) { i in
                    HStack(spacing: 14) {
                        ZStack {
                            Circle()
                                .fill(loadingChecks[i] ? BBColors.primarySoft : BBColors.surfaceAlt)
                                .frame(width: 46, height: 46)
                                .overlay(Circle().stroke(loadingChecks[i] ? BBColors.primary : BBColors.border, lineWidth: 2))
                            if loadingChecks[i] {
                                Image(systemName: "checkmark")
                                    .font(BBFont.font(18, .bold))
                                    .foregroundColor(BBColors.primary)
                            } else {
                                Image(systemName: loadingItems[i].icon)
                                    .font(BBFont.font(20))
                                    .foregroundColor(BBColors.textMuted)
                            }
                        }
                        Text(loadingItems[i].label)
                            .font(BBFont.font(17, .heavy))
                            .foregroundColor(loadingChecks[i] ? BBColors.text : BBColors.textMuted)
                        Spacer()
                    }
                    .opacity(loadingChecks[i] ? 1 : 0.4)
                    .animation(.easeOut(duration: 0.3).delay(Double(i) * 0.05), value: loadingChecks[i])
                }
            }
            .padding(.horizontal, 32)

            Spacer()
        }
        .padding(.horizontal, 24)
    }

    private let loadingItems: [(icon: String, label: String)] = [
        ("ruler.fill", "Analyzing body metrics"),
        ("flame.fill", "Estimating daily energy burn"),
        ("target", "Setting calorie target"),
        ("chart.pie.fill", "Distributing macronutrients"),
        ("drop.fill", "Personalizing hydration")
    ]

    // MARK: - Step: Overview

    private var overviewStep: some View {
        guard let p = plan else { return AnyView(EmptyView()) }
        return AnyView(
            ScrollView(showsIndicators: false) {
                VStack(spacing: 20) {
                    VStack(spacing: 8) {
                        Text("Your personal plan is ready")
                            .font(BBFont.font(14, .heavy))
                            .foregroundColor(BBColors.primary)
                            .padding(.horizontal, 16).padding(.vertical, 8)
                            .background(BBColors.primarySoft)
                            .cornerRadius(BBRadius.pill)

                        Text("Your Plan")
                            .font(.system(size: 36, weight: .heavy))
                            .foregroundColor(BBColors.text)
                    }
                    .padding(.top, 24)

                    // Summary icon card
                    VStack(spacing: 10) {
                        Image(systemName: "target")
                            .font(.system(size: 38))
                            .foregroundColor(.white)
                            .frame(width: 80, height: 80)
                            .background(BBColors.primary)
                            .cornerRadius(BBRadius.pill)
                        Text("Based on body metrics and personal goals")
                            .font(BBFont.font(15, .bold))
                            .foregroundColor(BBColors.textSecondary)
                            .multilineTextAlignment(.center)
                    }
                    .padding(24)
                    .frame(maxWidth: .infinity)
                    .background(BBColors.surfaceAlt)
                    .cornerRadius(BBRadius.xl)
                    .overlay(RoundedRectangle(cornerRadius: BBRadius.xl).stroke(BBColors.border, lineWidth: 2))
                    .background(RoundedRectangle(cornerRadius: BBRadius.xl).fill(BBColors.borderSubtle).offset(y: 8))
                    .padding(.bottom, 8)

                    // Macro grid
                    LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 14) {
                        MetricCard(color: BBColors.primary,   icon: "flame.fill", label: "Calories", value: "\(p.calories)", unit: "kcal")
                        MetricCard(color: BBColors.secondary, icon: "bolt.fill",  label: "Protein",  value: "\(p.protein)",  unit: "g/day")
                        MetricCard(color: BBColors.accent,    icon: "leaf.fill", label: "Carbs",    value: "\(p.carbs)",    unit: "g/day")
                        MetricCard(color: BBColors.info,      icon: "drop.fill",  label: "Fat",      value: "\(p.fat)",      unit: "g/day")
                    }

                    // Context row
                    HStack(spacing: 10) {
                        ContextPill(label: "BMR",          value: "\(p.bmr) kcal")
                        ContextPill(label: "Energy burn",  value: "\(p.tdee) kcal")
                        ContextPill(label: "Water target", value: "\(p.hydration) ml")
                    }

                    // Disclaimer
                    HStack(alignment: .top, spacing: 12) {
                        Image(systemName: "info.circle.fill")
                            .font(BBFont.font(18, .semibold))
                            .foregroundColor(BBColors.warning)
                        Text("Data from this app is for general reference only. Consult a doctor before changing your diet.")
                            .font(BBFont.font(13, .semibold))
                            .foregroundColor(BBColors.text)
                    }
                    .padding(16)
                    .background(BBColors.warningBg)
                    .cornerRadius(BBRadius.lg)
                    .overlay(RoundedRectangle(cornerRadius: BBRadius.lg).stroke(BBColors.warning, lineWidth: 2))
                    .background(RoundedRectangle(cornerRadius: BBRadius.lg).fill(BBColors.borderSubtle).offset(y: 6))
                    .padding(.bottom, 6)

                    if let err = errorMessage {
                        Text(err)
                            .font(BBFont.font(14, .bold))
                            .foregroundColor(BBColors.danger)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(14)
                            .background(BBColors.dangerBg)
                            .cornerRadius(BBRadius.md)
                    }

                    Button {
                        Task { await commitPlan() }
                    } label: {
                        if isSaving {
                            ProgressView().tint(.white).frame(maxWidth: .infinity)
                        } else {
                            Label("Commit to my goal", systemImage: "checkmark")
                                .frame(maxWidth: .infinity)
                        }
                    }
                    .buttonStyle(BBButtonStyle(
                        backgroundColor: BBColors.primary,
                        shadowColor: BBColors.primaryHover,
                        isEnabled: !isSaving
                    ))
                    .disabled(isSaving)
                    .padding(.bottom, 32)
                }
                .padding(.horizontal, 20)
            }
        )
    }

    // MARK: - Plan calculation (mirrors goal_plan.php client-side)

    private func buildPlan() {
        // Animate checklist items one by one, then show overview
        plan = PlanCalculator.build(
            age: age, gender: gender,
            weight: Double(weightKg), height: Double(heightCm),
            activity: activity, goalMode: goalMode,
            weeklyRate: goalMode == "maintain" ? 0 : weeklyRate
        )

        Task {
            for i in loadingItems.indices {
                try? await Task.sleep(nanoseconds: 500_000_000)
                loadingChecks[i] = true
            }
            try? await Task.sleep(nanoseconds: 400_000_000)
            withAnimation { step = .overview }
        }
    }

    private func commitPlan() async {
        guard let p = plan else { return }
        isSaving = true
        errorMessage = nil
        do {
            try await session.saveOnboarding(OnboardingPayload(
                gender: gender, age: age, height: heightCm, weight: weightKg,
                activityLevel: activity, goalMode: goalMode,
                weeklyRate: goalMode == "maintain" ? 0 : weeklyRate,
                targetWeight: Double(targetWeight) ?? nil
            ))
            session.needsOnboarding = false
        } catch {
            errorMessage = error.localizedDescription
        }
        isSaving = false
    }

    // MARK: - Navigation

    private func advance() {
        let ordered: [OnboardingStep] = [.gender, .age, .height, .weight, .activity, .goal, .pace, .loading, .overview]
        guard let idx = ordered.firstIndex(of: step) else { return }
        var next = ordered[idx + 1]
        // skip pace for maintain
        if next == .pace && goalMode == "maintain" {
            withAnimation { step = .loading }
            buildPlan()
            return
        }
        withAnimation { step = next }
        if next == .loading { buildPlan() }
    }

    private func goBack() {
        let ordered: [OnboardingStep] = [.gender, .age, .height, .weight, .activity, .goal, .pace, .loading, .overview]
        guard let idx = ordered.firstIndex(of: step), idx > 0 else { return }
        var prev = ordered[idx - 1]
        // skip pace going back if maintain
        if prev == .pace && goalMode == "maintain" { prev = .goal }
        withAnimation { step = prev }
    }

    // MARK: - Shared CTAs

    @ViewBuilder
    private func wizardPrimary(_ title: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(title).frame(maxWidth: .infinity)
        }
        .buttonStyle(BBButtonStyle(backgroundColor: BBColors.primary, shadowColor: BBColors.primaryHover))
    }
}

// MARK: - Wizard step enum

enum OnboardingStep: Equatable {
    case gender, age, height, weight, activity, goal, pace, loading, overview
}

// MARK: - Plan calculator (mirrors goal_plan.php)

struct PersonalPlan {
    let bmr: Int; let tdee: Int; let calories: Int
    let protein: Int; let carbs: Int; let fat: Int; let hydration: Int
}

enum PlanCalculator {
    static func build(age: Int, gender: String, weight: Double, height: Double,
                      activity: String, goalMode: String, weeklyRate: Double) -> PersonalPlan {
        let base = 10 * weight + 6.25 * height - 5.0 * Double(age)
        let bmr: Double
        switch gender {
        case "female": bmr = base - 161
        case "other":  bmr = base - 78
        default:       bmr = base + 5
        }
        let factors: [String: Double] = [
            "sedentary": 1.2, "lightly_active": 1.375, "moderately_active": 1.55,
            "very_active": 1.725, "extra_active": 1.9
        ]
        let tdee = bmr * (factors[activity] ?? 1.55)
        let dailyAdj: Double
        if goalMode == "maintain" {
            dailyAdj = 0
        } else {
            let abs = (weeklyRate * 7700.0) / 7.0
            dailyAdj = goalMode == "lose" ? -abs : abs
        }
        let calories = max(800, min(10000, Int((tdee + dailyAdj).rounded())))
        return PersonalPlan(
            bmr:       Int(bmr.rounded()),
            tdee:      Int(tdee.rounded()),
            calories:  calories,
            protein:   Int((Double(calories) * 0.30 / 4).rounded()),
            carbs:     Int((Double(calories) * 0.45 / 4).rounded()),
            fat:       Int((Double(calories) * 0.25 / 9).rounded()),
            hydration: Int((weight * 35 / 250).rounded()) * 250
        )
    }
}

// MARK: - Sub-views

private struct WizardCard<Content: View, Footer: View>: View {
    let kicker: String
    let title: String
    let subtitle: String
    @ViewBuilder let content: () -> Content
    @ViewBuilder let footer: () -> Footer

    var body: some View {
        VStack(spacing: 0) {
            ScrollView(showsIndicators: false) {
                VStack(spacing: 16) {
                    VStack(spacing: 12) {
                        Text(kicker)
                            .font(BBFont.font(13, .heavy))
                            .foregroundColor(BBColors.primary)
                            .padding(.horizontal, 14).padding(.vertical, 8)
                            .background(BBColors.primarySoft)
                            .cornerRadius(BBRadius.pill)

                        Text(title)
                            .font(.system(size: 32, weight: .heavy))
                            .foregroundColor(BBColors.text)
                            .multilineTextAlignment(.center)

                        Text(subtitle)
                            .font(BBFont.font(BBFont.base, .semibold))
                            .foregroundColor(BBColors.textSecondary)
                            .multilineTextAlignment(.center)
                    }
                    .padding(.top, 20)
                    .padding(.horizontal, 20)

                    content()
                        .padding(.horizontal, 20)
                }
                .padding(.bottom, 20)
            }

            footer()
                .padding(.horizontal, 20)
                .padding(.bottom, 24)
        }
    }
}

private struct ChoiceCard: View {
    let icon: String
    let label: String
    var detail: String = ""
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(spacing: 8) {
                Image(systemName: icon)
                    .font(.system(size: 28))
                    .foregroundColor(isSelected ? .white : BBColors.primary)
                Text(label)
                    .font(BBFont.font(15, .heavy))
                    .foregroundColor(isSelected ? .white : BBColors.text)
                if !detail.isEmpty {
                    Text(detail)
                        .font(.system(size: 11, weight: .bold))
                        .foregroundColor(isSelected ? .white.opacity(0.85) : BBColors.textSecondary)
                        .multilineTextAlignment(.center)
                }
            }
            .padding(.vertical, 16)
            .frame(maxWidth: .infinity, minHeight: 110)
            .background(isSelected ? BBColors.primary : BBColors.surfaceAlt)
            .cornerRadius(BBRadius.lg)
            .overlay(RoundedRectangle(cornerRadius: BBRadius.lg)
                .stroke(isSelected ? BBColors.primaryHover : BBColors.border, lineWidth: 2))
            .background(RoundedRectangle(cornerRadius: BBRadius.lg)
                .fill(isSelected ? BBColors.primaryHover : BBColors.borderSubtle).offset(y: 6))
            .offset(y: isSelected ? -3 : 0)
            .padding(.bottom, 6)
        }
        .animation(.interactiveSpring(response: 0.18, dampingFraction: 0.8), value: isSelected)
    }
}

private struct WideChoiceCard: View {
    let icon: String
    let label: String
    let detail: String
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 14) {
                Image(systemName: icon)
                    .font(.system(size: 22))
                    .foregroundColor(isSelected ? .white : BBColors.primary)
                    .frame(width: 44, height: 44)
                    .background(isSelected ? BBColors.primary : BBColors.surface)
                    .cornerRadius(BBRadius.pill)
                    .overlay(Circle().stroke(isSelected ? BBColors.primaryHover : BBColors.border, lineWidth: 2))
                VStack(alignment: .leading, spacing: 3) {
                    Text(label)
                        .font(BBFont.font(15, .heavy))
                        .foregroundColor(isSelected ? .white : BBColors.text)
                    Text(detail)
                        .font(BBFont.font(12, .semibold))
                        .foregroundColor(isSelected ? .white.opacity(0.80) : BBColors.textSecondary)
                }
                Spacer()
                if isSelected {
                    Image(systemName: "checkmark.circle.fill")
                        .foregroundColor(.white)
                        .font(.system(size: 20))
                }
            }
            .padding(14)
            .background(isSelected ? BBColors.primary : BBColors.surfaceAlt)
            .cornerRadius(BBRadius.lg)
            .overlay(RoundedRectangle(cornerRadius: BBRadius.lg)
                .stroke(isSelected ? BBColors.primaryHover : BBColors.border, lineWidth: 2))
            .background(RoundedRectangle(cornerRadius: BBRadius.lg)
                .fill(isSelected ? BBColors.primaryHover : BBColors.borderSubtle).offset(y: 5))
            .offset(y: isSelected ? -2 : 0)
            .padding(.bottom, 5)
        }
        .animation(.interactiveSpring(response: 0.18, dampingFraction: 0.8), value: isSelected)
    }
}

private struct MetricCard: View {
    let color: Color; let icon: String; let label: String; let value: String; let unit: String
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 6) {
                Image(systemName: icon)
                    .font(.system(size: 16))
                    .foregroundColor(color)
                Text(label).font(BBFont.font(15, .heavy)).foregroundColor(BBColors.textSecondary)
            }
            HStack(alignment: .lastTextBaseline, spacing: 4) {
                Text(value).font(.system(size: 38, weight: .heavy)).foregroundColor(BBColors.text)
                Text(unit).font(BBFont.font(15, .heavy)).foregroundColor(BBColors.textSecondary)
            }
        }
        .padding(18)
        .frame(maxWidth: .infinity, minHeight: 120, alignment: .leading)
        .background(BBColors.surfaceAlt)
        .cornerRadius(BBRadius.lg)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.lg).stroke(color, lineWidth: 2))
        .background(RoundedRectangle(cornerRadius: BBRadius.lg).fill(BBColors.borderSubtle).offset(y: 6))
        .padding(.bottom, 6)
    }
}

private struct ContextPill: View {
    let label: String; let value: String
    var body: some View {
        VStack(spacing: 4) {
            Text(label).font(.system(size: 11, weight: .heavy)).foregroundColor(BBColors.textSecondary)
            Text(value).font(BBFont.font(15, .heavy)).foregroundColor(BBColors.text)
        }
        .padding(12)
        .frame(maxWidth: .infinity)
        .background(BBColors.surface)
        .cornerRadius(BBRadius.md)
        .overlay(RoundedRectangle(cornerRadius: BBRadius.md).stroke(BBColors.border, lineWidth: 2))
        .background(RoundedRectangle(cornerRadius: BBRadius.md).fill(BBColors.borderSubtle).offset(y: 4))
        .padding(.bottom, 4)
    }
}

// MARK: - Preview

#Preview("Onboarding") {
    OnboardingView()
        .environmentObject(SessionStore.preview)
}
