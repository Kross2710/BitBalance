import Foundation
import Combine

@MainActor
final class SessionStore: ObservableObject {
    @Published private(set) var user: UserSession?
    @Published var isLoading = false
    @Published var errorMessage: String?

    private let api: APIClient

    init(api: APIClient) {
        self.api = api
    }

    func signIn(email: String, password: String) async {
        isLoading = true
        errorMessage = nil

        do {
            user = try await api.login(email: email, password: password)
        } catch {
            errorMessage = error.localizedDescription
        }

        isLoading = false
    }

    func restoreSession() async {
        isLoading = true
        errorMessage = nil

        do {
            user = try await api.loadCurrentUser()
        } catch {
            user = nil
        }

        isLoading = false
    }

    func signOut() async {
        do {
            try await api.logout()
        } catch {
            errorMessage = error.localizedDescription
        }

        user = nil
    }

    func loadDashboardSummary() async throws -> DashboardSummary {
        try await api.loadDashboardSummary()
    }

    func loadIntakeHistory(limit: Int = 50) async throws -> IntakeHistoryPayload {
        try await api.loadIntakeHistory(limit: limit)
    }

    func createIntake(_ payload: IntakeFormPayload) async throws -> IntakeMutationPayload {
        try await api.createIntake(payload)
    }

    func updateIntake(id: Int, payload: IntakeFormPayload) async throws -> IntakeMutationPayload {
        try await api.updateIntake(id: id, payload: payload)
    }

    func deleteIntake(id: Int) async throws -> IntakeDeletePayload {
        try await api.deleteIntake(id: id)
    }

    func loadProfile() async throws -> ProfilePayload {
        try await api.loadProfile()
    }

    func updateProfile(_ payload: ProfileUpdatePayload) async throws -> ProfilePayload {
        let updated = try await api.updateProfile(payload)
        user = updated.user
        return updated
    }

    // MARK: - AI Coach

    func loadConversations() async throws -> [Conversation] {
        try await api.loadConversations()
    }

    func loadMessages(conversationId: Int) async throws -> ConversationMessagesPayload {
        try await api.loadMessages(conversationId: conversationId)
    }

    func sendMessage(_ message: String, conversationId: Int = 0, imageData: Data? = nil) async throws -> SendMessagePayload {
        try await api.sendMessage(message, conversationId: conversationId, imageData: imageData)
    }

    func lookupBarcode(barcode: String) async throws -> BarcodeProductPayload {
        try await api.lookupBarcode(barcode: barcode)
    }

    func deleteConversation(id: Int) async throws -> DeleteConversationPayload {
        try await api.deleteConversation(id: id)
    }

    // MARK: - Friends & Social Hub

    func loadSocialHub() async throws -> SocialPollPayload {
        try await api.loadSocialHub()
    }

    func loadSocialLeaderboard(period: String = "weekly") async throws -> LeaderboardPayload {
        try await api.loadSocialLeaderboard(period: period)
    }

    func searchUsers(query: String) async throws -> [SearchUserResult] {
        try await api.searchUsers(query: query)
    }

    func sendFriendRequest(targetId: Int) async throws {
        try await api.sendFriendRequest(targetId: targetId)
    }

    func respondFriendRequest(requestId: Int, accept: Bool) async throws {
        try await api.respondFriendRequest(requestId: requestId, accept: accept)
    }

    func cancelFriendRequest(requestId: Int) async throws {
        try await api.cancelFriendRequest(requestId: requestId)
    }

    func unfriend(targetId: Int) async throws {
        try await api.unfriend(targetId: targetId)
    }
}
