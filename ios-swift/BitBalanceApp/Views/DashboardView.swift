import SwiftUI

struct DashboardView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var summary: DashboardSummary?
    @State private var errorMessage: String?

    var greetingText: String {
        let hour = Calendar.current.component(.hour, from: Date())
        let name = session.user?.firstName ?? "Friend"
        if hour < 12 {
            return "Good morning, \(name)! 👋"
        } else if hour < 17 {
            return "Good afternoon, \(name)! 👋"
        } else {
            return "Good evening, \(name)! 👋"
        }
    }

    var body: some View {
        NavigationStack {
            ZStack {
                // Subtle brand background gradient
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                ScrollView {
                    VStack(alignment: .leading, spacing: 20) {
                        // Dynamic Welcome Header Banner (3D Green Gradient Card)
                        if let _ = session.user {
                            HStack {
                                VStack(alignment: .leading, spacing: 6) {
                                    Text(greetingText)
                                        .font(.system(size: 24, weight: .black))
                                        .foregroundColor(.white)
                                    
                                    Text("Let's hit your health goals today!")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(.white.opacity(0.9))
                                }
                                Spacer()
                                Text("🔥")
                                    .font(.system(size: 44))
                            }
                            .padding(20)
                            .background(BBColors.primaryGradient)
                            .cornerRadius(BBRadius.lg)
                            .overlay(
                                RoundedRectangle(cornerRadius: BBRadius.lg)
                                    .stroke(BBColors.primaryHover, lineWidth: 2)
                                    .allowsHitTesting(false)
                            )
                            .background(
                                RoundedRectangle(cornerRadius: BBRadius.lg)
                                    .fill(BBColors.primaryHover.opacity(0.8))
                                    .offset(y: 8)
                            )
                            .shadow(color: Color.black.opacity(0.08), radius: 8, x: 0, y: 2)
                            .padding(.bottom, 8)
                        }

                        if let errorMessage {
                            HStack(spacing: 8) {
                                Image(systemName: "exclamationmark.triangle.fill")
                                Text(errorMessage)
                            }
                            .font(.system(size: 14, weight: .bold))
                            .bbAlert(isSuccess: false)
                        }

                        // 4 Separate Stat Cards in a 2-column Grid
                        let calorieGoal = Double(summary?.calorieGoal ?? 2000)
                        let proteinGoal = round((calorieGoal * 0.30) / 4.0)
                        let carbsGoal = round((calorieGoal * 0.45) / 4.0)
                        let fatGoal = round((calorieGoal * 0.25) / 9.0)

                        LazyVGrid(columns: [GridItem(.flexible(), spacing: 16), GridItem(.flexible(), spacing: 16)], spacing: 16) {
                            // 1. Calories Card
                            StatCard(
                                label: "CALORIES",
                                value: "\(summary?.totalCalories ?? 0)",
                                goalValue: summary?.calorieGoal.map { "\($0)" } ?? "2000",
                                unit: "kcal",
                                percentage: summary?.progressPercentage ?? 0,
                                color: BBColors.primary,
                                emoji: "🔥",
                                tintColor: BBColors.primary.opacity(0.12)
                            )
                            
                            // 2. Protein Card
                            StatCard(
                                label: "PROTEIN",
                                value: "\(Int(summary?.protein ?? 0))",
                                goalValue: "\(Int(proteinGoal))",
                                unit: "g",
                                percentage: Double(summary?.protein ?? 0) / proteinGoal * 100.0,
                                color: BBColors.secondary,
                                emoji: "🍗",
                                tintColor: BBColors.secondary.opacity(0.12)
                            )
                            
                            // 3. Carbs Card
                            StatCard(
                                label: "CARBS",
                                value: "\(Int(summary?.carbs ?? 0))",
                                goalValue: "\(Int(carbsGoal))",
                                unit: "g",
                                percentage: Double(summary?.carbs ?? 0) / carbsGoal * 100.0,
                                color: BBColors.accent,
                                emoji: "🍞",
                                tintColor: BBColors.accent.opacity(0.12)
                            )
                            
                            // 4. Fat Card
                            StatCard(
                                label: "FAT",
                                value: "\(Int(summary?.fat ?? 0))",
                                goalValue: "\(Int(fatGoal))",
                                unit: "g",
                                percentage: Double(summary?.fat ?? 0) / fatGoal * 100.0,
                                color: BBColors.danger,
                                emoji: "🥑",
                                tintColor: BBColors.danger.opacity(0.12)
                            )
                        }

                        // 3. Level & XP 3D Card
                        LevelCard(summary: summary)
                    }
                    .padding(20)
                }
            }
            .navigationTitle("Dashboard")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Log Out") {
                        Task {
                            await session.signOut()
                        }
                    }
                    .font(.system(size: 14, weight: .bold))
                    .foregroundColor(BBColors.danger)
                }
            }
            .onAppear {
                Task {
                    await loadSummary()
                }
            }
            .refreshable {
                await loadSummary()
            }
        }
    }

    private func loadSummary() async {
        do {
            summary = try await session.loadDashboardSummary()
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

// MARK: - Subcomponents

/// Premium 3D Stat Card
private struct StatCard: View {
    let label: String
    let value: String
    let goalValue: String
    let unit: String
    let percentage: Double
    let color: Color
    let emoji: String
    let tintColor: Color
    
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 8) {
                // Emoji Icon Badge
                Text(emoji)
                    .font(.system(size: 20))
                    .frame(width: 44, height: 44)
                    .background(tintColor)
                    .cornerRadius(12)
                    .overlay(
                        RoundedRectangle(cornerRadius: 12)
                            .stroke(color.opacity(0.3), lineWidth: 1)
                            .allowsHitTesting(false)
                    )
                
                VStack(alignment: .leading, spacing: 2) {
                    Text(label)
                        .font(.system(size: 11, weight: .heavy))
                        .foregroundColor(BBColors.textSecondary)
                    
                    HStack(alignment: .firstTextBaseline, spacing: 1) {
                        Text(value)
                            .font(.system(size: 22, weight: .black))
                            .foregroundColor(BBColors.text)
                        Text(unit)
                            .font(.system(size: 10, weight: .bold))
                            .foregroundColor(BBColors.textSecondary)
                    }
                }
            }
            
            VStack(alignment: .leading, spacing: 4) {
                // Progress Bar
                GeometryReader { geo in
                    ZStack(alignment: .leading) {
                        RoundedRectangle(cornerRadius: 4)
                            .fill(BBColors.surfaceAlt)
                            .frame(height: 8)
                        
                        RoundedRectangle(cornerRadius: 4)
                            .fill(color)
                            .frame(width: geo.size.width * CGFloat(min(max(percentage, 0) / 100.0, 1.0)), height: 8)
                    }
                }
                .frame(height: 8)
                
                HStack {
                    Text("Goal: \(goalValue)\(unit)")
                        .font(.system(size: 11, weight: .bold))
                        .foregroundColor(BBColors.textSecondary)
                    Spacer()
                    Text("\(Int(min(max(percentage, 0), 999.0)))%")
                        .font(.system(size: 11, weight: .black))
                        .foregroundColor(color)
                }
            }
        }
        .bbCard(radius: BBRadius.lg, padding: 14)
    }
}

/// Level & XP Progress Card
private struct LevelCard: View {
    let summary: DashboardSummary?
    
    var body: some View {
        HStack(spacing: 18) {
            // Level Star Badge
            ZStack {
                Image(systemName: "star.fill")
                    .font(.system(size: 56))
                    .foregroundColor(BBColors.accent)
                    .shadow(color: BBColors.accent.opacity(0.3), radius: 6, x: 0, y: 3)
                
                Text("\(summary?.currentLevel ?? 1)")
                    .font(.system(size: 18, weight: .black))
                    .foregroundColor(.white)
                    .offset(y: -2)
            }
            .padding(.leading, 4)
            
            VStack(alignment: .leading, spacing: 6) {
                Text("LEVEL")
                    .font(.system(size: 12, weight: .heavy))
                    .foregroundColor(BBColors.textSecondary)
                
                Text("\(summary?.totalXp ?? 0) XP")
                    .font(.system(size: 26, weight: .black))
                    .foregroundColor(BBColors.text)
                
                if let progress = summary?.xpProgressPercentage {
                    VStack(alignment: .leading, spacing: 4) {
                        GeometryReader { geo in
                            ZStack(alignment: .leading) {
                                RoundedRectangle(cornerRadius: 6)
                                    .fill(BBColors.surfaceAlt)
                                    .frame(height: 8)
                                
                                RoundedRectangle(cornerRadius: 6)
                                    .fill(BBColors.streakGradient)
                                    .frame(width: geo.size.width * CGFloat(min(max(Double(progress), 0) / 100.0, 1.0)), height: 8)
                            }
                        }
                        .frame(height: 8)
                        
                        Text("\(progress)% to Next Level")
                            .font(.system(size: 11, weight: .bold))
                            .foregroundColor(BBColors.textSecondary)
                    }
                    .padding(.top, 2)
                } else {
                    Text("Loading streak progress...")
                        .font(.system(size: 12, weight: .bold))
                        .foregroundColor(BBColors.textSecondary)
                }
            }
            Spacer()
        }
        .bbCard()
    }
}
