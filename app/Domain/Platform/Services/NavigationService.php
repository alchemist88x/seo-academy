<?php

namespace App\Domain\Platform\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class NavigationService
{
    /**
     * Build the structured sidebar navigation tree for the authenticated user and academy context.
     *
     * @return array<int, array{
     *     title: string,
     *     items: array<int, array{
     *         label: string,
     *         route: ?string,
     *         url: string,
     *         icon: string,
     *         active: bool,
     *         badge: ?string,
     *         children?: array<int, array{
     *             label: string,
     *             route: ?string,
     *             url: string,
     *             active: bool
     *         }>
     *     }>
     * }>
     */
    public function getNavigationForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $isAdmin = $user->hasRole('admin');
        $isTrainer = $user->hasRole('trainer');
        $isSupportStaff = $user->hasRole('support_staff');
        $isStudent = $user->hasRole('student');

        $groups = [];

        // 1. Overview Group (Universal for all authenticated roles)
        $overviewItems = [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'url' => route('dashboard'),
                'icon' => 'layout-dashboard',
                'active' => request()->routeIs('dashboard', 'admin.dashboard', 'trainer.dashboard', 'support.dashboard', 'student.dashboard'),
            ],
            [
                'label' => 'Notifications',
                'route' => 'notifications.index',
                'url' => route('notifications.index'),
                'icon' => 'bell',
                'active' => request()->routeIs('notifications.*'),
            ],
        ];

        $groups[] = [
            'title' => 'Overview',
            'items' => $overviewItems,
        ];

        // 2. Admin Navigation
        if ($isAdmin) {
            $academyItems = [
                [
                    'label' => 'Students',
                    'route' => 'admin.students.index',
                    'url' => route('admin.students.index'),
                    'icon' => 'users',
                    'active' => request()->routeIs('admin.students.*'),
                ],
                [
                    'label' => 'Trainers',
                    'route' => 'admin.trainers.index',
                    'url' => route('admin.trainers.index'),
                    'icon' => 'graduation-cap',
                    'active' => request()->routeIs('admin.trainers.*'),
                ],
                [
                    'label' => 'Members & Invitations',
                    'route' => 'admin.members.index',
                    'url' => route('admin.members.index'),
                    'icon' => 'user-check',
                    'active' => request()->routeIs('admin.members.*'),
                ],
            ];

            $curriculumItems = [
                [
                    'label' => 'Courses',
                    'route' => 'admin.courses.index',
                    'url' => route('admin.courses.index'),
                    'icon' => 'book-open',
                    'active' => request()->routeIs('admin.courses.*') && ! request()->query('manage_categories'),
                ],
                [
                    'label' => 'Course Categories',
                    'route' => 'admin.courses.index',
                    'url' => route('admin.courses.index', ['manage_categories' => 1]),
                    'icon' => 'folder',
                    'active' => request()->routeIs('admin.courses.*') && (bool) request()->query('manage_categories'),
                ],
                [
                    'label' => 'Enrollments',
                    'route' => 'admin.enrollments.index',
                    'url' => route('admin.enrollments.index'),
                    'icon' => 'clipboard-check',
                    'active' => request()->routeIs('admin.enrollments.*'),
                ],
                [
                    'label' => 'Assessments',
                    'route' => 'admin.assessments.index',
                    'url' => route('admin.assessments.index'),
                    'icon' => 'file-question',
                    'active' => request()->routeIs('admin.assessments.*'),
                ],
                [
                    'label' => 'Assignments',
                    'route' => 'admin.assignments.index',
                    'url' => route('admin.assignments.index'),
                    'icon' => 'file-text',
                    'active' => request()->routeIs('admin.assignments.*'),
                ],
                [
                    'label' => 'Question Banks',
                    'route' => 'admin.question-banks.index',
                    'url' => route('admin.question-banks.index'),
                    'icon' => 'database',
                    'active' => request()->routeIs('admin.question-banks.*'),
                ],
                [
                    'label' => 'Certificates',
                    'route' => 'admin.certificates.index',
                    'url' => route('admin.certificates.index'),
                    'icon' => 'award',
                    'active' => request()->routeIs('admin.certificates.*'),
                ],
            ];

            $systemItems = [
                [
                    'label' => 'Support Desk',
                    'route' => 'support.tickets.index',
                    'url' => route('support.tickets.index'),
                    'icon' => 'life-buoy',
                    'active' => request()->routeIs('support.*'),
                ],
                [
                    'label' => 'Academy Settings',
                    'route' => 'admin.academy.settings',
                    'url' => route('admin.academy.settings'),
                    'icon' => 'settings',
                    'active' => request()->routeIs('admin.academy.settings'),
                ],
                [
                    'label' => 'File Management',
                    'route' => 'admin.files.index',
                    'url' => route('admin.files.index'),
                    'icon' => 'folder',
                    'active' => request()->routeIs('admin.files.*'),
                ],
                [
                    'label' => 'Audit Logs',
                    'route' => 'admin.audit-logs.index',
                    'url' => route('admin.audit-logs.index'),
                    'icon' => 'shield-check',
                    'active' => request()->routeIs('admin.audit-logs.*'),
                ],
                [
                    'label' => 'System Health',
                    'route' => 'admin.system.health',
                    'url' => route('admin.system.health'),
                    'icon' => 'activity',
                    'active' => request()->routeIs('admin.system.health'),
                ],
                [
                    'label' => 'System & Email',
                    'route' => 'admin.system.settings',
                    'url' => route('admin.system.settings'),
                    'icon' => 'sliders',
                    'active' => request()->routeIs('admin.system.settings'),
                ],
                [
                    'label' => 'Backups & Recovery',
                    'route' => 'admin.system.backups',
                    'url' => route('admin.system.backups'),
                    'icon' => 'database',
                    'active' => request()->routeIs('admin.system.backups*'),
                ],
            ];

            $groups[] = [
                'title' => 'People & Access',
                'items' => $academyItems,
            ];

            $groups[] = [
                'title' => 'Curriculum & Records',
                'items' => $curriculumItems,
            ];

            $groups[] = [
                'title' => 'System & Operations',
                'items' => $systemItems,
            ];
        }

        // 3. Trainer Navigation
        if ($isTrainer && ! $isAdmin) {
            $teachingItems = [
                [
                    'label' => 'My Assigned Courses',
                    'route' => 'trainer.courses.index',
                    'url' => route('trainer.courses.index'),
                    'icon' => 'book-open',
                    'active' => request()->routeIs('trainer.courses.*'),
                ],
                [
                    'label' => 'My Students',
                    'route' => 'trainer.students.index',
                    'url' => route('trainer.students.index'),
                    'icon' => 'users',
                    'active' => request()->routeIs('trainer.students.*'),
                ],
                [
                    'label' => 'Assessments & Grading',
                    'route' => 'trainer.assessments.index',
                    'url' => route('trainer.assessments.index'),
                    'icon' => 'file-question',
                    'active' => request()->routeIs('trainer.assessments.*'),
                ],
                [
                    'label' => 'Assignments & Grading',
                    'route' => 'trainer.assignments.index',
                    'url' => route('trainer.assignments.index'),
                    'icon' => 'file-text',
                    'active' => request()->routeIs('trainer.assignments.*'),
                ],
                [
                    'label' => 'Question Banks',
                    'route' => 'trainer.question-banks.index',
                    'url' => route('trainer.question-banks.index'),
                    'icon' => 'database',
                    'active' => request()->routeIs('trainer.question-banks.*'),
                ],
            ];

            $groups[] = [
                'title' => 'Teaching',
                'items' => $teachingItems,
            ];
        }

        // 4. Support Staff Navigation
        if ($isSupportStaff && ! $isAdmin) {
            $supportItems = [
                [
                    'label' => 'Support Tickets',
                    'route' => 'support.tickets.index',
                    'url' => route('support.tickets.index'),
                    'icon' => 'life-buoy',
                    'active' => request()->routeIs('support.tickets.*'),
                ],
            ];

            $groups[] = [
                'title' => 'Support Desk',
                'items' => $supportItems,
            ];
        }

        // 5. Student Navigation
        if ($isStudent && ! $isAdmin && ! $isTrainer) {
            $learningItems = [
                [
                    'label' => 'My Courses',
                    'route' => 'student.courses.index',
                    'url' => route('student.courses.index'),
                    'icon' => 'book-open',
                    'active' => request()->routeIs('student.courses.*'),
                ],
                [
                    'label' => 'My Assignments',
                    'route' => 'student.assignments.index',
                    'url' => route('student.assignments.index'),
                    'icon' => 'file-text',
                    'active' => request()->routeIs('student.assignments.*'),
                ],
                [
                    'label' => 'My Certificates',
                    'route' => 'student.certificates.index',
                    'url' => route('student.certificates.index'),
                    'icon' => 'award',
                    'active' => request()->routeIs('student.certificates.*'),
                ],
            ];

            $groups[] = [
                'title' => 'Learning',
                'items' => $learningItems,
            ];
        }

        return $groups;
    }
}
