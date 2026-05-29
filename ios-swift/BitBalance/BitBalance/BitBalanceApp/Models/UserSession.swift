import Foundation

struct UserSession: Codable, Equatable {
    let userId: Int
    let handle: String
    let userName: String?
    let firstName: String
    let lastName: String?
    let email: String
    let role: String?
    let profileImage: String?
    let themePreference: String?
}

struct APIEnvelope<T: Decodable>: Decodable {
    let ok: Bool
    let data: T?
    let message: String?
}

struct EmptyPayload: Decodable {}

struct DashboardSummary: Codable {
    let totalCalories: Int
    let calorieGoal: Int?
    let progressPercentage: Double
    let protein: Double
    let carbs: Double
    let fat: Double
    let currentLevel: Int
    let totalXp: Int
    let xpProgressPercentage: Int?
}
