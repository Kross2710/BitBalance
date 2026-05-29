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

struct MacroTotals: Codable, Equatable {
    let protein: Double
    let carbs: Double
    let fat: Double
}

struct DailySummary: Codable, Equatable {
    let totalCalories: Int
    let calorieGoal: Int?
    let progressPercentage: Double
    let macros: MacroTotals
    let macroGoals: MacroTotals
}

struct IntakeEntry: Codable, Identifiable, Equatable {
    let id: Int
    let foodItem: String
    let calories: Int
    let protein: Double
    let carbs: Double
    let fat: Double
    let mealCategory: String
    let dateIntake: String?
    let isoDate: String?
}

struct IntakeHistoryPayload: Codable {
    let entries: [IntakeEntry]
    let dailySummary: DailySummary
}

struct IntakeMutationPayload: Codable {
    let entry: IntakeEntry?
    let dailySummary: DailySummary
}

struct IntakeDeletePayload: Codable {
    let deletedId: Int
    let dailySummary: DailySummary
}

struct IntakeFormPayload {
    let foodItem: String
    let calories: Int
    let protein: Double
    let carbs: Double
    let fat: Double
    let mealCategory: String
}

struct ProfileGoal: Codable, Equatable {
    let calorieGoal: Int
    let dateSet: String?
}

struct PhysicalInfo: Codable, Equatable {
    let age: Int?
    let gender: String?
    let weight: Double?
    let height: Double?
}

struct ProfilePayload: Codable {
    let user: UserSession
    let bio: String?
    let status: String?
    let goal: ProfileGoal?
    let physical: PhysicalInfo
}

struct ProfileUpdatePayload {
    let firstName: String
    let lastName: String
    let userName: String
    let email: String
    let bio: String
    let themePreference: String
    let calorieGoal: String
    let age: String
    let gender: String
    let weight: String
    let height: String
}

// MARK: - AI Coach

struct Conversation: Codable, Identifiable {
    let id: Int
    let title: String
    let createdAt: String
    let updatedAt: String
}

struct ChatMessage: Codable, Identifiable {
    let id: Int
    let role: String
    let content: String
    let imagePath: String?
    let createdAt: String
}

struct ConversationMessagesPayload: Codable {
    let conversation: Conversation
    let messages: [ChatMessage]
}

struct SendMessagePayload: Codable {
    let conversationId: Int
    let userMessage: ChatMessage
    let assistantMessage: ChatMessage
    let usageToday: Int
    let dailyLimit: Int
}

struct DeleteConversationPayload: Codable {
    let deletedId: Int
}

// MARK: - Barcode Product
struct BarcodeProductPayload: Codable {
    let found: Bool
    let barcode: String
    let productName: String?
    let brand: String?
    let servingSize: String?
    let kcalPerServing: Int?
    let kcalPer100g: Double?
    let protein: Double?
    let carbs: Double?
    let fat: Double?
    let sugar: Double?
    let imageUrl: String?
    let source: String?
    let cacheHit: Bool?
    let latencyMs: Int?
}

// MARK: - Friends & Social Hub Models
struct FriendItem: Codable, Identifiable, Equatable {
    var id: Int { userId }
    let userId: Int
    let userName: String
    let profileImage: String?
    let currentLevel: Int
    let totalXp: Int
    let loggingStreak: Int
    let weeklyXp: Int
    let requestId: Int?
    let friendsSince: String?

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case userName = "user_name"
        case profileImage = "profile_image"
        case currentLevel = "current_level"
        case totalXp = "total_xp"
        case loggingStreak = "logging_streak"
        case weeklyXp = "weekly_xp"
        case requestId = "request_id"
        case friendsSince = "friends_since"
    }
}

struct LeaderboardEntry: Codable, Identifiable, Equatable {
    var id: Int { userId }
    let userId: Int
    let userName: String
    let profileImage: String?
    let currentLevel: Int
    let totalXp: Int
    let loggingStreak: Int
    let weeklyXp: Int
    let scoreXp: Int
    let rank: Int
    let isCurrentUser: Bool

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case userName = "user_name"
        case profileImage = "profile_image"
        case currentLevel = "current_level"
        case totalXp = "total_xp"
        case loggingStreak = "logging_streak"
        case weeklyXp = "weekly_xp"
        case scoreXp = "score_xp"
        case rank
        case isCurrentUser = "is_current_user"
    }
}

struct PendingFriendRequest: Codable, Identifiable, Equatable {
    let id: Int // request_id
    let createdAt: String
    let userId: Int
    let userName: String
    let profileImage: String?
    let currentLevel: Int
    let loggingStreak: Int?

    enum CodingKeys: String, CodingKey {
        case id = "request_id"
        case createdAt = "created_at"
        case userId = "user_id"
        case userName = "user_name"
        case profileImage = "profile_image"
        case currentLevel = "current_level"
        case loggingStreak = "logging_streak"
    }
}

struct SearchUserResult: Codable, Identifiable, Equatable {
    var id: Int { userId }
    let userId: Int
    let userName: String
    let profileImage: String?
    let currentLevel: Int
    let loggingStreak: Int
    let relationship: String

    enum CodingKeys: String, CodingKey {
        case userId = "user_id"
        case userName = "user_name"
        case profileImage = "profile_image"
        case currentLevel = "current_level"
        case loggingStreak = "logging_streak"
        case relationship
    }
}

struct SocialPollPayload: Codable {
    let friends: [FriendItem]
    let pendingIn: [PendingFriendRequest]
    let pendingOut: [PendingFriendRequest]

    enum CodingKeys: String, CodingKey {
        case friends
        case pendingIn = "pending_in"
        case pendingOut = "pending_out"
    }
}

struct LeaderboardPayload: Codable {
    let period: String
    let leaders: [LeaderboardEntry]
}

struct SearchUsersPayload: Codable {
    let results: [SearchUserResult]
}


