<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Form>
 */
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
     * Indicate that the form is accepting submissions.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the form is not accepting submissions.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the form should have a basic schema
     */
    public function withBasicSchema(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'schema' => [
                    [
                        'id' => Str::ulid()->toString(),
                        'order' => 1,
                        'label' => 'Name',
                        'rules' => ['required'],
                    ],
                    [
                        'id' => Str::ulid()->toString(),
                        'order' => 2,
                        'label' => 'Email',
                        'rules' => ['required', 'email'],
                    ],
                    [
                        'id' => Str::ulid()->toString(),
                        'order' => 3,
                        'label' => 'Message',
                        'rules' => ['required'],
                    ],
                ],
            ];
        });
    }
}
