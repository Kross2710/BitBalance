import SwiftUI

struct DashboardView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var summary: DashboardSummary?
    @State private var errorMessage: String?

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    if let user = session.user {
                        Text("Hello, \(user.firstName)")
                            .font(.largeTitle.bold())
                    }

                    if let errorMessage {
                        Text(errorMessage)
                            .font(.footnote.weight(.semibold))
                            .foregroundStyle(.red)
                    }

                    SummaryCard(
                        title: "Today",
                        value: "\(summary?.totalCalories ?? 0) kcal",
                        subtitle: goalSubtitle
                    )
                    SummaryCard(
                        title: "Macros",
                        value: "\(Int(summary?.protein ?? 0))g P",
                        subtitle: "\(Int(summary?.carbs ?? 0))g carbs • \(Int(summary?.fat ?? 0))g fat"
                    )
                    SummaryCard(
                        title: "Level",
                        value: "\(summary?.currentLevel ?? 1)",
                        subtitle: "\(summary?.totalXp ?? 0) XP"
                    )
                }
                .padding(20)
            }
            .navigationTitle("Dashboard")
            .toolbar {
                Button("Log Out") {
                    Task {
                        await session.signOut()
                    }
                }
            }
            .task {
                await loadSummary()
            }
            .refreshable {
                await loadSummary()
            }
        }
    }

    private var goalSubtitle: String {
        guard let summary else {
            return "Loading dashboard..."
        }

        if let goal = summary.calorieGoal {
            return "\(Int(summary.progressPercentage))% of \(goal) kcal goal"
        }

        return "No calorie goal set"
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

private struct SummaryCard: View {
    let title: String
    let value: String
    let subtitle: String

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(title)
                .font(.headline)
                .foregroundStyle(.secondary)

            Text(value)
                .font(.title.bold())

            Text(subtitle)
                .font(.subheadline)
                .foregroundStyle(.secondary)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(18)
        .background(.background)
        .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
        .shadow(color: .black.opacity(0.12), radius: 12, x: 0, y: 6)
    }
}
