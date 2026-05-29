import Foundation

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
}
