import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/student.dart';

final selectedStudentProvider =
    NotifierProvider<SelectedStudentController, Student?>(
      SelectedStudentController.new,
    );

class SelectedStudentController extends Notifier<Student?> {
  @override
  Student? build() => null;

  void select(Student student) => state = student;
  void clear() => state = null;
}
