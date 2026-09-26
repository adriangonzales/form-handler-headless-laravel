<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FormFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $exampleNames = [
            'Contact Form',
            'Inquiry / Quote Request Form',
            'Consultation Booking Form',
            'Newsletter Signup Form',
            'Callback Request Form',
            'Job Application Form',
            'Employee Onboarding Form',
            'Leave / Time-Off Request Form',
            'Performance Evaluation Form',
            'Expense Reimbursement Form',
            'Exit Interview Form',
            'Donation Form',
            'Checkout / Payment Form',
            'Grant Application Form',
            'Membership Renewal Form',
            'Refund / Return Request Form',
            'Event Registration Form',
            'Course Enrollment Form',
            'Student Admission Form',
            'RSVP Form',
            'Volunteer Sign-Up Form',
            'Customer Satisfaction Survey Form',
            'Product Feedback Form',
            'Bug Report Form',
            'Complaint / Grievance Form',
            'Market Research Questionnaire',
            'Consent / Release Form',
            'Non-Disclosure Agreement (NDA) Form',
            'Patient Intake / Health History Form',
            'Terms of Service Acceptance Form',
            'Liability Waiver Form',
        ];

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement($exampleNames),
            'active' => fake()->boolean(),
            'schema' => [],
            'settings' => [],
        ];
    }

    /**
     * Indicate that the user is suspended.
     */
    public function basicSchema(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'schema' => [
                    Str::ulid()->toString() => [
                        'label' => 'Name',
                        'rules' => ['required'],
                    ],
                    Str::ulid()->toString() => [
                        'label' => 'Email',
                        'rules' => ['required', 'email'],
                    ],
                    Str::ulid()->toString() => [
                        'label' => 'Message',
                        'rules' => ['required'],
                    ],
                ],
            ];
        });
    }
}
