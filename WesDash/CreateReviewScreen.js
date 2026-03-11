import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  Button,
  StyleSheet,
  Alert,
} from 'react-native';
import { BASE_URL } from '../config';

export default function CreateReviewScreen({ route, navigation }) {
  const { orderId } = route.params;
  const [reviewText, setReviewText] = useState('');
  const [rating, setRating] = useState('');

  const handleSubmitReview = async () => {
    if (!reviewText || !rating) {
      Alert.alert('Error', 'Please provide both a review and a rating.');
      return;
    }

    try {
      const response = await fetch(`${BASE_URL}/WesDashAPI/save_review.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderId, reviewText, rating }),
        credentials: 'include',
      });
      const data = await response.json();

      if (data.success) {
        Alert.alert('Success', 'Review submitted successfully.');
        navigation.navigate('DashboardScreen');
      } else {
        Alert.alert('Error', data.message || 'Failed to submit review.');
      }
    } catch (error) {
      console.error('Error submitting review:', error);
      Alert.alert('Error', 'Something went wrong. Please try again.');
    }
  };

  const handleCancelReview = async () => {
    try {
      const response = await fetch(`${BASE_URL}/WesDashAPI/cancel_review_prompt.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderId }),
        credentials: 'include',
      });
      const data = await response.json();

      if (data.success) {
        navigation.navigate('DashboardScreen');
      } else {
        Alert.alert('Error', data.message || 'Failed to cancel review prompt.');
      }
    } catch (error) {
      console.error('Error canceling review prompt:', error);
      Alert.alert('Error', 'Something went wrong. Please try again.');
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Write a Review</Text>

      <TextInput
        style={styles.input}
        placeholder="Enter your review"
        value={reviewText}
        onChangeText={setReviewText}
      />

      <TextInput
        style={styles.input}
        placeholder="Enter your rating (1-5)"
        value={rating}
        onChangeText={setRating}
        keyboardType="numeric"
      />

      <Button title="Submit Review" onPress={handleSubmitReview} />
      <Button title="Cancel" onPress={handleCancelReview} color="red" />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 20,
    backgroundColor: '#fff',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 20,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 5,
    padding: 10,
    marginBottom: 15,
  },
});