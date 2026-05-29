import SwiftUI

struct ProfileView: View {
    @EnvironmentObject private var session: SessionStore

    @State private var firstName = ""
    @State private var lastName = ""
    @State private var handle = ""
    @State private var email = ""
    @State private var bio = ""
    @State private var themePreference = "system"
    @State private var calorieGoal = ""
    @State private var age = ""
    @State private var gender = ""
    @State private var weight = ""
    @State private var height = ""
    @State private var isLoading = false
    @State private var isSaving = false
    @State private var message: String?
    @State private var messageIsError = false

    @FocusState private var focusedField: Field?
    enum Field {
        case firstName, lastName, handle, email, bio, calorieGoal, age, weight, height
    }

    private let themes = [
        ("system", "System"),
        ("light", "Light"),
        ("dark", "Dark")
    ]

    private let genders = [
        ("", "Not set"),
        ("male", "Male"),
        ("female", "Female")
    ]

    var body: some View {
        NavigationStack {
            ZStack {
                // Brand background gradient
                BBColors.backgroundGradient
                    .ignoresSafeArea()
                
                ScrollView(showsIndicators: false) {
                    VStack(spacing: 24) {
                        
                        // 1. Conic Gradient Avatar Header
                        VStack(spacing: 12) {
                            ZStack {
                                Circle()
                                    .fill(BBColors.surface)
                                    .frame(width: 120, height: 120)
                                    .overlay(
                                        Circle()
                                            .stroke(
                                                AngularGradient(
                                                    colors: [BBColors.primary, BBColors.secondary, BBColors.primary],
                                                    center: .center
                                                ),
                                                lineWidth: 4
                                            )
                                    )
                                    .background(
                                        Circle()
                                            .fill(BBColors.primaryHover)
                                            .offset(y: 6)
                                    )
                                    .shadow(color: Color.black.opacity(0.1), radius: 6, x: 0, y: 3)
                                
                                Text(avatarInitials)
                                    .font(.system(size: 38, weight: .black))
                                    .foregroundColor(BBColors.primary)
                            }
                            
                            VStack(spacing: 2) {
                                Text(handle.isEmpty ? "@username" : "@\(handle)")
                                    .font(.system(size: 16, weight: .heavy))
                                    .foregroundColor(BBColors.textSecondary)
                                
                                if !email.isEmpty {
                                    Text(email)
                                        .font(.system(size: 12, weight: .bold))
                                        .foregroundColor(BBColors.textMuted)
                                }
                            }
                        }
                        .padding(.top, 16)
                        .padding(.bottom, 8)
                        
                        if isLoading && firstName.isEmpty {
                            ProgressView()
                                .padding()
                        } else {
                            // 2. Account Details Card
                            VStack(alignment: .leading, spacing: 16) {
                                SectionHeader(
                                    emoji: "👤",
                                    title: "Personal Information",
                                    subtitle: "Manage your display name and credentials",
                                    color: BBColors.secondary
                                )
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("First Name")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("First name", text: $firstName)
                                        .textInputAutocapitalization(.words)
                                        .focused($focusedField, equals: .firstName)
                                        .bbInput(isFocused: focusedField == .firstName)
                                }
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Last Name")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Last name", text: $lastName)
                                        .textInputAutocapitalization(.words)
                                        .focused($focusedField, equals: .lastName)
                                        .bbInput(isFocused: focusedField == .lastName)
                                }
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Username Handle")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Handle", text: $handle)
                                        .textInputAutocapitalization(.never)
                                        .autocorrectionDisabled()
                                        .focused($focusedField, equals: .handle)
                                        .bbInput(isFocused: focusedField == .handle)
                                }
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Bio")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Tell us about yourself", text: $bio, axis: .vertical)
                                        .lineLimit(2...4)
                                        .focused($focusedField, equals: .bio)
                                        .bbInput(isFocused: focusedField == .bio)
                                }
                            }
                            .bbCard()
                            
                            // 3. Goal Card
                            VStack(alignment: .leading, spacing: 16) {
                                SectionHeader(
                                    emoji: "🎯",
                                    title: "Nutrition Goal",
                                    subtitle: "Customize your daily caloric requirements",
                                    color: BBColors.primary
                                )
                                
                                VStack(alignment: .leading, spacing: 6) {
                                    Text("Daily Calorie Target (kcal)")
                                        .font(.system(size: 13, weight: .bold))
                                        .foregroundColor(BBColors.textSecondary)
                                    TextField("Daily calories", text: $calorieGoal)
                                        .keyboardType(.numberPad)
                                        .focused($focusedField, equals: .calorieGoal)
                                        .bbInput(isFocused: focusedField == .calorieGoal)
                                }
                            }
                            .bbCard()
                            
                            // 4. Physical Info Card (2x2 grid layout)
                            VStack(alignment: .leading, spacing: 16) {
                                SectionHeader(
                                    emoji: "📏",
                                    title: "Physical Stats",
                                    subtitle: "Update your height, weight, and age metrics",
                                    color: BBColors.accent
                                )
                                
                                LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
                                    VStack(alignment: .leading, spacing: 6) {
                                        Text("Age (years)")
                                            .font(.system(size: 13, weight: .bold))
                                            .foregroundColor(BBColors.textSecondary)
                                        TextField("Age", text: $age)
                                            .keyboardType(.numberPad)
                                            .focused($focusedField, equals: .age)
                                            .bbInput(isFocused: focusedField == .age)
                                    }
                                    
                                    VStack(alignment: .leading, spacing: 6) {
                                        Text("Gender")
                                            .font(.system(size: 13, weight: .bold))
                                            .foregroundColor(BBColors.textSecondary)
                                        
                                        Picker("Gender", selection: $gender) {
                                            ForEach(genders, id: \.0) { option in
                                                Text(option.1).tag(option.0)
                                            }
                                        }
                                        .pickerStyle(.menu)
                                        .frame(maxWidth: .infinity, minHeight: 46)
                                        .background(BBColors.surfaceAlt)
                                        .cornerRadius(BBRadius.md)
                                        .overlay(
                                            RoundedRectangle(cornerRadius: BBRadius.md)
                                                .stroke(BBColors.border, lineWidth: 2)
                                                .allowsHitTesting(false)
                                        )
                                    }
                                    
                                    VStack(alignment: .leading, spacing: 6) {
                                        Text("Weight (kg)")
                                            .font(.system(size: 13, weight: .bold))
                                            .foregroundColor(BBColors.textSecondary)
                                        TextField("Weight", text: $weight)
                                            .keyboardType(.decimalPad)
                                            .focused($focusedField, equals: .weight)
                                            .bbInput(isFocused: focusedField == .weight)
                                    }
                                    
                                    VStack(alignment: .leading, spacing: 6) {
                                        Text("Height (cm)")
                                            .font(.system(size: 13, weight: .bold))
                                            .foregroundColor(BBColors.textSecondary)
                                        TextField("Height", text: $height)
                                            .keyboardType(.decimalPad)
                                            .focused($focusedField, equals: .height)
                                            .bbInput(isFocused: focusedField == .height)
                                    }
                                }
                            }
                            .bbCard()
                            
                            // 5. Appearance Card (custom visual card selection)
                            VStack(alignment: .leading, spacing: 16) {
                                SectionHeader(
                                    emoji: "🎨",
                                    title: "Appearance",
                                    subtitle: "Toggle your mobile theme preference",
                                    color: Color(hex: "A855F7")
                                )
                                
                                HStack(spacing: 12) {
                                    ForEach(themes, id: \.0) { opt in
                                        let isSelected = themePreference == opt.0
                                        let icon = opt.0 == "light" ? "sun.max.fill" : opt.0 == "dark" ? "moon.fill" : "desktopcomputer"
                                        Button {
                                            themePreference = opt.0
                                        } label: {
                                            VStack(spacing: 8) {
                                                Image(systemName: icon)
                                                    .font(.system(size: 18))
                                                Text(opt.1)
                                                    .font(.system(size: 13, weight: .bold))
                                            }
                                            .frame(maxWidth: .infinity)
                                            .padding(.vertical, 12)
                                            .background(isSelected ? BBColors.primary.opacity(0.12) : BBColors.surfaceAlt)
                                            .foregroundColor(isSelected ? BBColors.primary : BBColors.text)
                                            .cornerRadius(BBRadius.md)
                                            .overlay(
                                                RoundedRectangle(cornerRadius: BBRadius.md)
                                                    .stroke(isSelected ? BBColors.primary : BBColors.border, lineWidth: 2)
                                                    .allowsHitTesting(false)
                                            )
                                        }
                                    }
                                }
                            }
                            .bbCard()
                            
                            // 6. 3D Danger Zone Section Card
                            VStack(alignment: .leading, spacing: 16) {
                                SectionHeader(
                                    emoji: "⚠️",
                                    title: "Danger Zone",
                                    subtitle: "Permanent actions that cannot be undone",
                                    color: BBColors.danger
                                )
                                
                                Button {
                                    // Inform user this action is native RMIT scoped
                                } label: {
                                    Text("Delete Account")
                                        .frame(maxWidth: .infinity)
                                }
                                .buttonStyle(BBButtonStyle(
                                    backgroundColor: BBColors.danger,
                                    shadowColor: Color(hex: "B91C1C"),
                                    isEnabled: true
                                ))
                            }
                            .padding(16)
                            .background(BBColors.dangerBg.opacity(0.4))
                            .cornerRadius(BBRadius.lg)
                            .overlay(
                                RoundedRectangle(cornerRadius: BBRadius.lg)
                                    .stroke(BBColors.dangerBorder, lineWidth: 2)
                                    .allowsHitTesting(false)
                            )
                            .background(
                                RoundedRectangle(cornerRadius: BBRadius.lg)
                                    .fill(BBColors.dangerBorder.opacity(0.8))
                                    .offset(y: 8)
                            )
                            .shadow(color: Color.black.opacity(0.06), radius: 8, x: 0, y: 2)
                            .padding(.bottom, 8)
                        }
                        
                        // Status alert/saved banner
                        if let message {
                            HStack(spacing: 10) {
                                Image(systemName: messageIsError ? "exclamationmark.triangle.fill" : "checkmark.circle.fill")
                                    .font(.system(size: 16, weight: .bold))
                                Text(message)
                                    .font(.system(size: 14, weight: .bold))
                            }
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .bbAlert(isSuccess: !messageIsError)
                            .transition(.opacity.combined(with: .scale))
                        }
                        
                        // Save Button
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
                                Text("Save Profile")
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
            .navigationTitle("Profile")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    if focusedField != nil {
                        Button("Done") {
                            focusedField = nil
                        }
                        .font(.system(size: 14, weight: .bold))
                        .foregroundColor(BBColors.primary)
                    } else {
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
            }
            .task {
                if firstName.isEmpty {
                    await load()
                }
            }
            .refreshable {
                await load()
            }
        }
    }

    private var avatarInitials: String {
        let f = firstName.first.map { String($0) } ?? ""
        let l = lastName.first.map { String($0) } ?? ""
        let initials = f + l
        return initials.isEmpty ? "U" : initials.uppercased()
    }

    private var isValid: Bool {
        !firstName.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty &&
        !lastName.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty &&
        !handle.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty &&
        !email.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    private func load() async {
        isLoading = true
        message = nil

        do {
            apply(try await session.loadProfile())
        } catch {
            show(error.localizedDescription, isError: true)
        }

        isLoading = false
    }

    private func save() async {
        isSaving = true
        message = nil

        let payload = ProfileUpdatePayload(
            firstName: firstName.trimmingCharacters(in: .whitespacesAndNewlines),
            lastName: lastName.trimmingCharacters(in: .whitespacesAndNewlines),
            userName: handle.trimmingCharacters(in: .whitespacesAndNewlines),
            email: email.trimmingCharacters(in: .whitespacesAndNewlines),
            bio: bio.trimmingCharacters(in: .whitespacesAndNewlines),
            themePreference: themePreference,
            calorieGoal: calorieGoal.trimmingCharacters(in: .whitespacesAndNewlines),
            age: age.trimmingCharacters(in: .whitespacesAndNewlines),
            gender: gender,
            weight: weight.trimmingCharacters(in: .whitespacesAndNewlines),
            height: height.trimmingCharacters(in: .whitespacesAndNewlines)
        )

        do {
            apply(try await session.updateProfile(payload))
            show("Profile saved successfully!", isError: false)
        } catch {
            show(error.localizedDescription, isError: true)
        }

        isSaving = false
    }

    private func apply(_ payload: ProfilePayload) {
        firstName = payload.user.firstName
        lastName = payload.user.lastName ?? ""
        handle = payload.user.handle
        email = payload.user.email
        bio = payload.bio ?? ""
        themePreference = payload.user.themePreference ?? "system"
        calorieGoal = payload.goal.map { String($0.calorieGoal) } ?? ""
        age = payload.physical.age.map { String($0) } ?? ""
        gender = payload.physical.gender ?? ""
        weight = payload.physical.weight.map { format($0) } ?? ""
        height = payload.physical.height.map { format($0) } ?? ""
    }

    private func show(_ text: String, isError: Bool) {
        message = text
        messageIsError = isError
        
        // Auto-dismiss after 4 seconds
        Task {
            try? await Task.sleep(nanoseconds: 4_000_000_000)
            if message == text {
                withAnimation {
                    message = nil
                }
            }
        }
    }

    private func format(_ value: Double) -> String {
        if value.rounded() == value {
            return String(Int(value))
        }

        return String(format: "%.1f", value)
    }
}

// MARK: - Reusable Profile Section Header
private struct SectionHeader: View {
    let emoji: String
    let title: String
    let subtitle: String
    let color: Color
    
    var body: some View {
        HStack(spacing: 12) {
            // Icon Badge
            Text(emoji)
                .font(.system(size: 20))
                .frame(width: 44, height: 44)
                .background(color.opacity(0.12))
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(color.opacity(0.3), lineWidth: 1)
                        .allowsHitTesting(false)
                )
            
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.system(size: 15, weight: .black))
                    .foregroundColor(BBColors.text)
                Text(subtitle)
                    .font(.system(size: 11, weight: .bold))
                    .foregroundColor(BBColors.textSecondary)
            }
        }
        .padding(.bottom, 4)
    }
}
