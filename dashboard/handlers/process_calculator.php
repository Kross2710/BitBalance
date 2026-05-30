<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the initialization file
require_once __DIR__ . '/../../include/init.php';
// Include the database configuration (only when needed, instead of including it in init.php to save resources)
require_once __DIR__ . '/../../include/db_config.php';

// Function to calculate BMR based on Mifflin-St Jeor Equation
function calculateBMR($age, $gender, $weight, $height)
{
    if ($gender == 'female') {
        return 10 * $weight + 6.25 * $height - 5 * $age - 161;
    } elseif ($gender == 'other') {
        return 10 * $weight + 6.25 * $height - 5 * $age - 78;
    } else {
        return 10 * $weight + 6.25 * $height - 5 * $age + 5;
    }
}
// Function to calculate TDEE based on activity level
function calculateTDEE($bmr, $activity_level)
{
    switch ($activity_level) {
        case 'sedentary':
            return $bmr * 1.2;
        case 'lightly_active':
            return $bmr * 1.375;
        case 'moderately_active':
            return $bmr * 1.55;
        case 'very_active':
            return $bmr * 1.725;
        case 'extra_active':
            return $bmr * 1.9;
        default:
            return $bmr; // Default to BMR if no valid activity level is provided
    }
}
// Function to calculate BMI
function calculateBMI($weight, $height)
{
    if ($height <= 0) {
        return 0; // Avoid division by zero
    }
    return $weight / (($height / 100) ** 2); // Height is in cm, convert to meters
}
// Function to display table of TDEE based on activity level
function calculateTDEEAll($bmr, $activity_level)
{
    $activity_levels = [
        'sedentary' => 1.2,
        'lightly_active' => 1.375,
        'moderately_active' => 1.55,
        'very_active' => 1.725,
        'extra_active' => 1.9
    ];

    $tdee_values = [];
    foreach ($activity_levels as $level => $factor) {
        $tdee_values[$level] = round($bmr * $factor, 2);
    }

    return $tdee_values;
}
// Function to calculate Ideal Weight using G.J. Hamwi Formula, 
// B.J. Devine Formula, J.D. Robinson Formula, D.R. Miller Formula
// and then give user range of ideal weight
function calculateIdealWeight($height, $gender)
{
    $results = [];

    // Convert height from cm to inches for calculations
    $height_in_inches = $height / 2.54;

    // Inches over 5 feet (60 inches)
    $inches_over_5ft = $height_in_inches - 60;

    $averageForOther = function ($femaleValue, $maleValue) use ($gender) {
        if ($gender === 'female') {
            return $femaleValue;
        }
        if ($gender === 'other') {
            return ($femaleValue + $maleValue) / 2;
        }
        return $maleValue;
    };

    // G.J. Hamwi Formula
    $hamwi = $averageForOther(45.5 + 2.2 * $inches_over_5ft, 48.0 + 2.7 * $inches_over_5ft);
    $results['Hamwi'] = round($hamwi, 1);

    // B.J. Devine Formula
    $devine = $averageForOther(45.5 + 2.3 * $inches_over_5ft, 50.0 + 2.3 * $inches_over_5ft);
    $results['Devine'] = round($devine, 1);

    // J.D. Robinson Formula
    $robinson = $averageForOther(48.67 + 1.7 * $inches_over_5ft, 52.0 + 1.9 * $inches_over_5ft);
    $results['Robinson'] = round($robinson, 1);

    // D.R. Miller Formula
    $miller = $averageForOther(53.1 + 1.36 * $inches_over_5ft, 56.2 + 1.41 * $inches_over_5ft);
    $results['Miller'] = round($miller, 1);

    // Get the lowest and highest ideal weight
    $range = [
        'min' => min($results),
        'max' => max($results),
        'formulas' => $results
    ];

    return $range;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $activity_level = trim($_POST['activity_level'] ?? '');

    $validGenders = ['male', 'female', 'other'];
    $validActivityLevels = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active'];

    // Validate inputs not empty
    if (empty($age) || empty($gender) || empty($weight) || empty($height) || empty($activity_level)) {
        $error_message = "Please fill in all fields.";
    } else {
        // Validate inputs are numeric and positive
        if (!is_numeric($age) || $age <= 0 || filter_var($age, FILTER_VALIDATE_INT) === false) {
            $error_message = "Please enter a valid whole number for age.";
        } elseif (!in_array($gender, $validGenders, true)) {
            $error_message = "Please select a valid gender.";
        } elseif (!is_numeric($weight) || $weight <= 0) {
            $error_message = "Weight must be a positive number.";
        } elseif (!is_numeric($height) || $height <= 0) {
            $error_message = "Height must be a positive number.";
        } elseif (!in_array($activity_level, $validActivityLevels, true)) {
            $error_message = "Please select a valid activity level.";
        }

        // If there are validation errors, redirect back with error message
        if (isset($error_message)) {
            header("Location: ../dashboard-calculator.php?error=" . urlencode($error_message));
            exit();
        }

        $age = (int) $age;
        $weight = (float) $weight;
        $height = (float) $height;

        // If no errors, proceed with calculations
        // Calculate BMI,BMR,TDEE, and Ideal Weight
        $bmi = calculateBMI($weight, $height);
        $bmr = calculateBMR($age, $gender, $weight, $height);
        $tdee = calculateTDEE($bmr, $activity_level);
        $tdee_all = calculateTDEEAll($bmr, $activity_level);
        $ideal_weight = calculateIdealWeight($height, $gender);

        // Prepare data to display
        $result = [
            'age' => $age,
            'gender' => $gender,
            'weight' => $weight,
            'height' => $height,
            'bmi' => round($bmi, 2),
            'tdee' => round($tdee, 2),
            'tdee_all' => $tdee_all,
            'activity_level' => $activity_level,
            'ideal_weight' => $ideal_weight
        ];
        // Store result in session to display on the results page
        $_SESSION['calculator_result'] = $result;

        // Redirect to results page
        header("Location: ../dashboard-calculator.php");
        exit();
    }
}
?>
